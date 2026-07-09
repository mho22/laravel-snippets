/// <reference lib="webworker" />

import { loadWebRuntime } from '@php-wasm/web';
import { PHP, setPhpIniEntries, type StreamedPHPResponse } from '@php-wasm/universal';

declare const self : DedicatedWorkerGlobalScope;


interface WorkerRequest
{
	id : number;
	code : string;
	action ? : 'run' | 'tokenize' | 'rotate';
};

interface ErrorDetail
{
	message : string;
	stack : string | null;
	name : string | null;
};

interface Code
{
	wrapper : string;
	snippetSource : string;
}


const BUNDLE_URL = new URL( '../laravel.zip', import.meta.url );
const BUNDLE_DIR = '/bundle';
const INIT_PATH = `${BUNDLE_DIR}/bootstrap/snippet-init.php`;
const CONTEXT_PATH = `${BUNDLE_DIR}/bootstrap/snippet-context.php`;


const SNIPPET_PATH = '/tmp/snippet.php';


function describeError( error : unknown ) : ErrorDetail
{
	if( error && typeof error === 'object' )
	{
		const err = error as Partial<Error>;

		return {
			message : err.message ?? String( error ),
			stack : err.stack ?? null,
			name : err.name ?? null,
		};
	}

	return { message : String( error ), stack : null, name : null };
}


self.addEventListener( 'error', event =>
{
	const detail = {
		message : event.message || '(no message)',
		filename : event.filename || null,
		lineno : event.lineno ?? null,
		colno : event.colno ?? null,
		error : event.error ? describeError( event.error ) : null,
	};

	console.error( '[ snippet-worker ] window.error :', detail, event );

	self.postMessage( { type : 'fatal', stage : 'error-event', ...detail } );
} );


self.addEventListener( 'unhandledrejection', event =>
{
	const detail = describeError( event.reason );

	console.error( '[ snippet-worker ] unhandledrejection :', detail, event );

	self.postMessage( { type : 'fatal', stage : 'unhandledrejection', ...detail } );
} );


const PROGRESS_BYTES_ESTIMATE = 70 * 1024 * 1024;
const PROGRESS_BYTES_CAP = 95;

let bytesReceived = 0;
let bytesTotal = 0;
let lastProgressPost = 0;


function postProgress() : void
{
	const now = performance.now();

	if( now - lastProgressPost < 100 ) return;

	lastProgressPost = now;

	const denom = Math.max( bytesTotal, PROGRESS_BYTES_ESTIMATE );

	const percentage = Math.min( PROGRESS_BYTES_CAP, Math.floor( ( bytesReceived / denom ) * PROGRESS_BYTES_CAP ) );

	self.postMessage( { type : 'progress', percent : percentage } );
}


const originalFetch = self.fetch.bind( self );

self.fetch = async function trackedFetch( input : RequestInfo | URL, init ? : RequestInit ) : Promise<Response>
{
	const response = await originalFetch( input, init );

	if( ! response.ok || ! response.body ) return response;

	const contentLength = parseInt( response.headers.get( 'content-length' ) || '0', 10 );

	if( contentLength > 0 ) bytesTotal += contentLength;

	const progress = new TransformStream<Uint8Array, Uint8Array>( {

		transform( chunk, controller )
		{
			bytesReceived += chunk.byteLength;

			postProgress();

			controller.enqueue( chunk );
		}
	} );

	return new Response( response.body.pipeThrough( progress ), {
		status : response.status,
		statusText : response.statusText,
		headers : response.headers,
	});
};


const runtimeFactory = () => loadWebRuntime( '8.5', { extensions : [ 'intl' ] } );


const PHP_INI_BASE = {
	display_errors : '0',
	html_errors : '0',
	log_errors : '0',
};


let php : PHP;

let initError : unknown = null;

try
{
	const runtimeId = await runtimeFactory();

	php = new PHP( runtimeId );

	await setPhpIniEntries( php, PHP_INI_BASE );

	await installBundle( php );

	self.fetch = originalFetch;

	self.postMessage( { type : 'ready' });
}
catch( error )
{
	initError = error;

	console.error( '[ snippet-worker ] init failed :', error );

	self.postMessage( {
		type : 'fatal',
		stage : 'init',
		message : describeError( error ).message,
		stack : describeError( error ).stack,
	} );
}

self.onmessage = async ( event : MessageEvent<WorkerRequest> ) =>
{
	const { id, code, action = 'run' } = event.data;

	if( initError )
	{
		self.postMessage( {
			type : 'fatal',
			stage : 'init',
			message : describeError( initError ).message,
			stack : describeError( initError ).stack,
		} );

		return;
	}

	if( action === 'tokenize' )
	{
		await handleTokenize( id, code );

		return;
	}
	if( action === 'rotate' )
	{
		await handleRotate( id );

		return;
	}

	const { wrapper, snippetSource } = prepareCode( code );

	const tRunStart = performance.now();

	try
	{
		php.writeFile( SNIPPET_PATH, snippetSource );

		const response = await php.runStream( { code : wrapper } );

		await reply( id, response, tRunStart );
	}
	catch( error : any )
	{
		self.postMessage( {
			type : 'result',
			id,
			stdout : '',
			stderr : String( error?.message || error ),
			exitCode : -1,
			tRun : performance.now() - tRunStart
		} );
	}
};


async function handleRotate( id : number ) : Promise<void>
{
	const t0 = performance.now();

	try
	{
		const newRuntime = await runtimeFactory();

		const tCreate = performance.now() - t0;

		await php.hotSwapPHPRuntime( newRuntime );

		const tSwap = performance.now() - t0 - tCreate;

		await setPhpIniEntries( php, PHP_INI_BASE );

		const tTotal = performance.now() - t0;

		self.postMessage( { type : 'rotated', id, tCreate, tSwap, tTotal } );
	}
	catch( error )
	{
		self.postMessage( {
			type : 'rotated',
			id,
			tCreate : -1,
			tSwap : -1,
			tTotal : performance.now() - t0,
			error : describeError( error ),
		} );
	}
}


async function handleTokenize( id : number, code : string ) : Promise<void>
{
	const hasOpenTag = /^<\?(?:php\b|=)/.test( code );

	const source = hasOpenTag ? code : `<?php\n${code}`;

	const b64 = base64FromUtf8( source );

	const phpCode = `<?php
		error_reporting(0);
		$src = base64_decode('${b64}');
		$tokens = token_get_all($src);
		${hasOpenTag ? '' : 'array_shift($tokens);'}
		$out = [];
		foreach ($tokens as $t) {
			$out[] = is_array($t) ? [token_name($t[0]), $t[1]] : [null, $t];
		}
		echo json_encode($out);
	`;

	try
	{
		const response = await php.runStream( { code : phpCode } );

		let tokens : Array<[ string | null, string ]> | null = null;

		try
		{
			tokens = JSON.parse( await response.stdoutText );
		}
		catch
		{
		}

		self.postMessage( { type : 'tokens', id, tokens } );
	}
	catch
	{
		self.postMessage( { type: 'tokens', id, tokens : null } );
	}
}


function base64FromUtf8( source : string ) : string
{
	const bytes = new TextEncoder().encode( source );

	let binary = '';

	for( const byte of bytes ) binary += String.fromCharCode( byte );

	return btoa( binary );
}


async function installBundle( php : PHP ) : Promise<void>
{
	const response = await fetch( BUNDLE_URL );

	if( ! response.ok ) throw new Error( `Bundle fetch failed : ${response.status}` );

	const zipBytes = new Uint8Array( await response.arrayBuffer() );

	const zipPath = '/tmp/laravel-bundle.zip';

	php.writeFile( zipPath, zipBytes );

	const unzip = await php.runStream( {
		code : `<?php
			$zip = new ZipArchive();
			if ($zip->open(${JSON.stringify(zipPath)}) !== TRUE) {
				fwrite(STDERR, 'Bundle unzip failed');
				exit(1);
			}
			$zip->extractTo(${JSON.stringify(BUNDLE_DIR)});
			$zip->close();
			unlink(${JSON.stringify(zipPath)});
		`,
	} );

	if( await unzip.exitCode !== 0 ) throw new Error( `Bundle unzip failed : ${await unzip.stderrText}` );
}


function stripPhpOpen( code : string ) : string
{
	return code.replace( /^\s*<\?php\s*\n?/, '' );
}


function prepareCode( code : string ) : Code
{
	const wrapper = `<?php
namespace {
	ini_set( 'display_errors', '0' );

	( static function () : void { require ${JSON.stringify(INIT_PATH)}; } )();

	require ${JSON.stringify(CONTEXT_PATH)};

	$__pre = get_defined_vars();

	$__rewritten = \\__autodump_rewrite( file_get_contents( ${JSON.stringify(SNIPPET_PATH)} ) );

	if( $__rewritten !== null )
	{
		file_put_contents( ${JSON.stringify(SNIPPET_PATH)}, $__rewritten );
	}

	ob_start();

	$__ret = require ${JSON.stringify(SNIPPET_PATH)};

	$__vars = array_filter(
		get_defined_vars(),
		static fn( $v, string $n ) : bool =>
			$n[0] !== '_'
			&& $n !== 'GLOBALS'
			&& $n !== 'argv'
			&& $n !== 'argc'
			&& ( ! array_key_exists( $n, $__pre ) || $__pre[ $n ] !== $v ),
		ARRAY_FILTER_USE_BOTH
	);

	if( $__ret !== 1 )
	{
		dump( $__ret );
	}
	elseif( $__rewritten === null )
	{
		$__last = array_key_last( $__vars );

		if( $__last !== null )
		{
			dump( $__vars[ $__last ] );
		}
		elseif( ob_get_length() === 0 && ! empty( $GLOBALS[ '__declared_classes' ] ?? [] ) )
		{
			foreach( $GLOBALS[ '__declared_classes' ] as $__cls )
			{
				dump( 'Defined: ' . $__cls );
			}
		}
	}

	ob_end_flush();
}
`;
	const snippetSource = `<?php\n${stripPhpOpen(code)}\n`;

	return { wrapper, snippetSource };
}


async function reply( id : number, response : StreamedPHPResponse, tRunStart : number ) : Promise<void>
{
	const stdout = await response.stdoutText;
	const stderr = await response.stderrText;
	const exitCode = await response.exitCode;

	self.postMessage( {
		type : 'result',
		id,
		stdout,
		stderr,
		exitCode,
		tRun : performance.now() - tRunStart
	} );
}

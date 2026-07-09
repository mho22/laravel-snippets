<script setup lang="ts">

import { onMounted, ref } from 'vue';
import { ansiToHtml, buildHighlightedHtml, escapeHtml, getCaretLineCol, setCaretLineCol } from '@/runtime/highlight';
import { onWorkerProgress, prewarmWorker, runPhp, runTokenize } from '@/runtime/php';


interface SnippetProps
{
	php : string;
	highlighted : string;
	preamble ? : string;
}


const props = defineProps<SnippetProps>();


const outlineAttributes = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
const copySVG = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-linecap="round" aria-hidden="true" class="size-5"><path d="M6.2474 6.25033V2.91699H17.0807V13.7503H13.7474M13.7474 6.25033V17.0837H2.91406V6.25033H13.7474Z"/></svg>';
const checkSVG = `<svg ${outlineAttributes} class="size-5"><path d="M20 6 9 17l-5-5"/></svg>`;
const playSVG = `<svg ${outlineAttributes} class="h-[0.95rem] w-[0.95rem]"><polygon points="6 3 20 12 6 21 6 3"/></svg>`;
const resetSVG = `<svg ${outlineAttributes} class="h-[0.95rem] w-[0.95rem]"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>`;
const spinnerSVG = `<svg ${outlineAttributes} class="h-[0.95rem] w-[0.95rem] origin-center animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;

const codeRef = ref<HTMLElement | null>( null );
const running = ref( false );
const mode = ref<'idle' | 'reset'>( 'idle' );
const status = ref( '' );
const outputHtml = ref<string | null>( null );
const copied = ref( false );

let generation = 0;
let debounceTimer : number | null = null;


function readSource(): string
{
	const element = codeRef.value;

	if( ! element ) return '';

	return Array.from( element.querySelectorAll<HTMLElement>( ':scope > .line' ) ).map( line =>
	{
		const clone = line.cloneNode( true ) as HTMLElement;

		clone.querySelector( '.line-number' )?.remove();

		return ( clone.textContent ?? '' ).replace( /\u00a0/g, ' ' );
	} )
	.join( '\n' );
}


function structuralPrefixEnd( source : string ) : number
{
	let i = 0;


	const openRe = /^[ \t\r\n]*<\?php[ \t\r\n]*/;

	const om = source.match( openRe );

	if( om ) i += om[ 0 ].length;


	const declRe = /^declare\s*\([^)]*\)\s*;[ \t\r\n]*/;

	const dm = source.slice( i ).match( declRe );

	if( dm ) i += dm[ 0 ].length;


	const nsRe = /^namespace\s+[\w\\]+\s*[;{][ \t\r\n]*/;

	const nm = source.slice( i ).match( nsRe );

	if( nm ) i += nm[ 0 ].length;


	const skipRe = /^(?:[ \t]*\r?\n|[ \t]*\/\/[^\n]*\n?|[ \t]*\/\*[\s\S]*?\*\/[ \t]*\r?\n?|use\s+(?:function\s+|const\s+)?[\\\w\s,{}]+?;[ \t]*\r?\n?)+/;

	const sm = source.slice( i ).match( skipRe );

	if( sm ) i += sm[ 0 ].length;


	return i;
}


function bodyStartsWithClassMember( body : string ) : boolean
{
	const firstLine = body.split( '\n' ).map( line => line.trim() ).find( line => line !== '' && ! line.startsWith( '//' ) && ! line.startsWith( '/*' ) && !line.startsWith( '*' ) );

	if( ! firstLine ) return false;

	return /^(public|protected|private|abstract|final|static|readonly)\b/.test( firstLine );
}


function bodyUsesThisAtTopLevel( body : string ) : boolean
{
	if( !/\$this\b/.test( body ) ) return false;

	if( /\bclass\s+\w+\b[^;]*\{/.test( body ) ) return false;

	const n = body.length;

	let i = 0;

	let depth = 0;

	while( i < n )
	{
		const c = body[ i ];

		if( c === '/' && body[ i + 1 ] === '/' )
		{
			while( i < n && body[ i ] !== '\n' ) i++;

			continue;
		}

		if( c === '/' && body[ i + 1 ] === '*' )
		{
			i += 2;

			while( i < n - 1 && ! ( body[ i ] === '*' && body[ i + 1 ] === '/' ) ) i++;

			i += 2;

			continue;
		}

		if( c === '#' && body[ i + 1 ] !== '[' )
		{
			while( i < n && body[ i ] !== '\n' ) i++;

			continue;
		}
		if( c === "'" )
		{
			i++;

			while( i < n )
			{
				if( body[ i ] === '\\' ) { i += 2; continue; }
				if( body[ i ] === "'" ) { i++; break; }

				i++;
			}

			continue;
		}
		if( c === '"' )
		{
			i++;

			while( i < n )
			{
				if( body[ i ] === '\\' ) { i += 2; continue; }
				if( body[ i ] === '"' ) { i++; break; }

				i++;
			}
			continue;
		}
		if( c === '{') { depth++; i++; continue; }
		if( c === '}') { depth--; i++; continue; }

		if( depth === 0 && c === '$' && body.startsWith('$this', i) && !/[A-Za-z0-9_]/.test(body[i + 5] ?? '') ) return true;

		i++;
	}

	return false;
}


function buildExecutable() : string
{
	const visible = readSource();
	const prefixEnd = structuralPrefixEnd( visible );
	const prefix = visible.slice( 0, prefixEnd );
	const body = visible.slice( prefixEnd );

	const preamble = props.preamble?.trim() ?? '';
	const useLines = preamble ? preamble + '\n' : '';

	const wrapName = '__Snippet_' + Math.random().toString( 36 ).slice( 2 );

	if( bodyStartsWithClassMember( body ) ) return prefix + useLines + 'class ' + wrapName + ' {\n' + body + '\n}\n';

	if( bodyUsesThisAtTopLevel( body ) ) return prefix + useLines + 'class ' + wrapName + ' {\npublic function __snippetRun() {\n' + body + '\n}\n}\n';

	return prefix + useLines + body;
}


async function rehighlight() : Promise<void>
{
	const element = codeRef.value;

	if( ! element ) return;

	const gen = generation;

	const source = readSource();

	const reply = await runTokenize( source );

	if( gen !== generation ) return;

	if( ! reply || !Array.isArray( reply.tokens ) ) return;

	const caret = getCaretLineCol(element);

	element.innerHTML = buildHighlightedHtml(reply.tokens);

	if( caret && document.activeElement === element ) setCaretLineCol( element, caret );
}


function onInput() : void
{
	generation++;

	if( mode.value === 'reset' )
	{
		mode.value = 'idle';
		status.value = '';
		outputHtml.value = null;
	}

	if( debounceTimer !== null ) window.clearTimeout( debounceTimer );

	debounceTimer = window.setTimeout( () => void rehighlight(), 300 );
}


async function execute() : Promise<void>
{
	if( running.value ) return;

	running.value = true;
	outputHtml.value = '';

	const unsubProgress = onWorkerProgress( progress => status.value = progress < 100 ? `${progress}%` : '' );

	let signalOutput = '';
	let signalHasStderr = false;

	try
	{
		const result = await runPhp(buildExecutable());

		const stdoutPlain = ( result.stdout ?? '').replace( /\x1b\[[0-9;]*m/g, '' ).trim();
		const stderrPlain = ( result.stderr ?? '').replace( /\x1b\[[0-9;]*m/g, '' ).trim();

		signalHasStderr = !! result.stderr;

		signalOutput = [ stdoutPlain, stderrPlain ].filter( Boolean ).join( '\n' ) || '(no output)';

		const parts : string[] = [];

		if( result.stdout ) parts.push( ansiToHtml( result.stdout ) );
		if( result.stderr ) parts.push( `<span class="block text-[#ff7b72]">${escapeHtml( result.stderr )}</span>`, );

		outputHtml.value = parts.join('\n') || '(no output)';

		status.value = result.exitCode === 0 ? `${Math.round(result.tRun)} ms` : `exit ${result.exitCode} · ${Math.round(result.tRun)} ms`;
	}
	catch( error )
	{
		const msg = error instanceof Error ? error.message : error && typeof error === 'object' && 'message' in error ? String( ( error as { message : unknown } ).message ) : String( error );

		console.error( '[laravel-snippet]', error );

		outputHtml.value = `<span class="block text-[#ff7b72]">${escapeHtml(msg)}</span>`;

		status.value = 'error';
		signalHasStderr = true;
		signalOutput = '';
	}
	finally
	{
		unsubProgress();
		running.value = false;
		mode.value = 'reset';

		codeRef.value?.dispatchEvent( new CustomEvent( 'laravel-snippet:complete', {
			bubbles : true,
			detail: { status : status.value, output : signalOutput, hasStderr : signalHasStderr }
		} ) );
	}
}


function reset() : void
{
	outputHtml.value = null;
	status.value = '';
	mode.value = 'idle';
}


async function copy() : Promise<void>
{
	try
	{
		await navigator.clipboard.writeText( readSource() );

		copied.value = true;

		window.setTimeout( () => ( copied.value = false ), 1500 );
	}
	catch
	{
	}
}


onMounted( () =>
{
	const element = codeRef.value;

	if( ! element ) return;

	element.innerHTML = props.highlighted;

	for( const number of element.querySelectorAll<HTMLElement>( '.line-number' ) )
	{
		number.setAttribute('contenteditable', 'false');
	}

	const idle = ( window as Window & { requestIdleCallback ? : (callback : () => void, options ? : { timeout : number } ) => number } ).requestIdleCallback;

	if( typeof idle === 'function' )
	{
		idle( () => prewarmWorker(), { timeout : 2000 } );
	}
	else
	{
		window.setTimeout( prewarmWorker, 200 );
	}
} );


const preClasses = 'laravel-snippet-output relative z-1 m-0 max-h-[32em] overflow-x-auto border-t border-[#62605b40] px-5 py-6 font-[JetBrains_Mono,ui-monospace,SFMono-Regular,monospace] text-[13px] leading-[1.7] whitespace-pre text-[#BFC7D5]';

const buttonClasses = 'inline-flex h-[1.9rem] w-[1.9rem] cursor-pointer items-center justify-center rounded-md border-none bg-transparent p-0 text-white/55 hover:bg-white/8 hover:text-white/85';

</script>

<template>
	<div class="code-block-wrapper relative">

		<pre><code ref="codeRef" contenteditable="plaintext-only" v-bind:spellcheck="false" class="torchlight focus:outline-none" v-on:input="onInput" /><div v-if="outputHtml !== null" v-bind:class="preClasses" v-html="outputHtml" /></pre>

		<div class="absolute top-3 right-3 z-2 inline-flex items-center gap-[0.35rem]">

			<span class="laravel-snippet-status font-mono text-[0.7rem] whitespace-nowrap text-white/55">{{ status }}</span>

			<button
				type="button"
				class="laravel-snippet-copy"
				v-bind:class="[ buttonClasses, copied ? 'text-[#5fdd7c]!' : '']"
				aria-label="Copy code"
				v-on:click="copy"
				v-html="copied ? checkSVG : copySVG"
			/>

			<button
				type="button"
				class="laravel-snippet-run"
				v-bind:class="buttonClasses"
				v-bind:aria-label="running ? 'Running snippet' : ( mode === 'reset' ? 'Clear output' : 'Run snippet' )"
				v-bind:aria-busy="running"
				v-on:click="mode === 'reset' ? reset() : execute()"
				v-html="running ? spinnerSVG : ( mode === 'reset' ? resetSVG : playSVG )"
			/>

		</div>

	</div>

</template>

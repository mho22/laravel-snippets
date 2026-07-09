import { expect, test } from '@playwright/test';
import { mkdirSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';


type Bucket = | 'ran-ok' | 'ran-with-stderr' | 'ran-exit-nonzero' | 'worker-error' | 'no-output' | 'never-completed';


interface SnippetResult
{
	page : string;
	index : number;
	bucket : Bucket;
	status : string;
	outputPreview ? : string;
}


function enumeratePages() : string[]
{
	return readdirSync( MARKDOWN_DIR ).filter( file => file.endsWith( '.md' ) ).map( file => file.replace( /\.md$/, '' ) ).sort();
}

function classify( status: string, outputText: string, stderrCount: number ) : Bucket
{
	if( status === 'error' ) return 'worker-error';

	if( status.startsWith( 'exit ' ) )
	{
		const trimmed = outputText.trim();

		const hasOutput = trimmed !== '' && trimmed !== '(no output)';

		if( status.startsWith( 'exit 1 ' ) && stderrCount === 0 && hasOutput ) return 'ran-ok';

		return 'ran-exit-nonzero';
	}

	if( /^\d+\s+ms$/.test( status ) )
	{
		if( stderrCount > 0 ) return 'ran-with-stderr';

		if( outputText.trim() === '' || outputText.trim() === '(no output)' ) return 'no-output';

		return 'ran-ok';
	}

	return 'never-completed';
}


test.describe( 'classify', () =>
{
	test( 'error → worker-error', () => expect( classify( 'error', '', 0 ) ).toBe( 'worker-error' ) );

	test( '"<n> ms" with output and no stderr → ran-ok', () => expect( classify( '42 ms', 'something', 0 ) ).toBe( 'ran-ok' ) );

	test( '"<n> ms" with stderr → ran-with-stderr', () => expect( classify( '42 ms', 'whatever', 1 ) ).toBe( 'ran-with-stderr' ) );

	test( 'exit 1 with output and no stderr → ran-ok (dd / Benchmark::dd)', () => expect( classify( 'exit 1 · 10 ms', 'something', 0 ) ).toBe( 'ran-ok' ) );

	test( 'exit 1 with stderr → ran-exit-nonzero', () => expect( classify( 'exit 1 · 10 ms', 'something', 1 ) ).toBe( 'ran-exit-nonzero' ) );

	test( '"<n> ms" with empty output → no-output', () =>
	{
		expect( classify( '42 ms', '', 0 ) ).toBe( 'no-output' );

		expect( classify( '42 ms', '(no output)', 0 ) ).toBe( 'no-output' );
	} );

	test( 'exit ≠ 1 → ran-exit-nonzero regardless of output', () =>
	{
		expect( classify( 'exit 255 · 10 ms', 'something', 0 ) ).toBe( 'ran-exit-nonzero' );

		expect( classify( 'exit 2 · 10 ms', '', 0 ) ).toBe( 'ran-exit-nonzero' );
	} );

	test( 'transitional statuses → never-completed', () =>
	{
		expect( classify( '', '', 0 ) ).toBe( 'never-completed' );

		expect( classify( 'Running…', '', 0 ) ).toBe( 'never-completed' );

		expect( classify( '42%', '', 0 ) ).toBe( 'never-completed' );
	} );
} );


const __filename = fileURLToPath( import.meta.url );
const __dirname = dirname( __filename );

const MARKDOWN_DIR = resolve( __dirname, '../resources/markdown/13.x' );
const RESULTS_DIR = resolve( __dirname, 'results' );

const PAGE_LIMIT = process.env.SNIPPET_PAGE_LIMIT ? Number( process.env.SNIPPET_PAGE_LIMIT ) : undefined;
const PER_PAGE_LIMIT = process.env.SNIPPET_LIMIT ? Number( process.env.SNIPPET_LIMIT ) : undefined;
const PAGE_FILTER = process.env.SNIPPET_PAGE ? process.env.SNIPPET_PAGE.split( ',' ).map( string => string.trim() ) : undefined;
const STATUS_POLL_TIMEOUT = 15_000;
const PW_WORKERS = Number( process.env.PW_WORKERS ?? '2' );



mkdirSync( RESULTS_DIR, { recursive : true } );

const allPages = enumeratePages();
const filteredPages = PAGE_FILTER ? allPages.filter( page => PAGE_FILTER.includes( page ) ) : allPages;
const pages = PAGE_LIMIT ? filteredPages.slice( 0, PAGE_LIMIT ) : filteredPages;


for( let shard = 0; shard < PW_WORKERS; shard++ )
{
	const assigned = pages.filter( ( value, index ) => index % PW_WORKERS === shard );

	if( assigned.length === 0 ) continue;

	test( `snippets sweep shard ${shard + 1}/${PW_WORKERS} (${assigned.length} pages)`, async ( { page } ) =>
	{
		test.setTimeout( 45 * 60 * 1000 );

		page.on( 'pageerror', error => console.log( `[shard ${shard} pageerror]`, error.message ) );

		page.on( 'console', message =>
		{
			const type = message.type();
			const text = message.text();

			if( type === 'error' || text.includes( '[snippet-worker]' ) ) console.log( `[shard ${shard} ${type}]`, text );
		} );

		for( let i = 0; i < assigned.length; i++ )
		{
			const slug = assigned[ i ];

			const results: SnippetResult[] = [];

			const outFile = join( RESULTS_DIR, `${slug}.json` );

			const flush = () => writeFileSync( outFile, JSON.stringify( results, null, 2 ) );

			if( i === 0 )
			{
				await page.goto( `/docs/13.x/${slug}`, { waitUntil : 'domcontentloaded' } );

				await page.waitForFunction(
					() =>
					{
						const sweepWindow = window as { rotateWorker ? : unknown; inertiaVisit ? : unknown; };

						return ( typeof sweepWindow.rotateWorker === 'function' && typeof sweepWindow.inertiaVisit === 'function' );
					},
					null,
					{ timeout : 15_000 },
				);
			}
			else
			{
				await page.evaluate( target => ( window as unknown as { inertiaVisit : ( url : string ) => Promise<void>; } ).inertiaVisit( target ), `/docs/13.x/${slug}` );

				await page.waitForURL( `**/docs/13.x/${slug}` );

				const rotated = await page.evaluate( async () =>
				{
					const sweepWindow = window as unknown as { rotateWorker: () => Promise<{ tCreate : number; tSwap : number; tTotal : number; error ? : { message : string }; }> };
					return sweepWindow.rotateWorker();
				} );

				if( rotated.error ) throw new Error( `Worker rotation failed before ${slug}: ${rotated.error.message}` );
			}

			const hasSnippets = await page.waitForSelector( '.laravel-snippet .laravel-snippet-run', { timeout : 15_000 } ).then( () => true ).catch( () => false );

			if( ! hasSnippets)
			{
				flush();

				continue;
			}

			const snippets = page.locator( '.laravel-snippet' );

			const total = await snippets.count();

			const limit = PER_PAGE_LIMIT ? Math.min( total, PER_PAGE_LIMIT ) : total;

			for( let i = 0; i < limit; i++ )
			{
				const snippet = snippets.nth( i );

				const statusEl = snippet.locator( '.laravel-snippet-status' );

				let bucket: Bucket = 'never-completed';
				let status = '';
				let outputPreview: string | undefined;

				try
				{
					const detail = await snippet.evaluate( ( element, milliseconds ) => new Promise<{ status : string; output : string; hasStderr : boolean; }>( ( resolve, reject ) =>
					{
						const button = element.querySelector( '.laravel-snippet-run', ) as HTMLButtonElement | null;

						if( ! button )
						{
							reject( new Error( 'no run button' ) );

							return;
						}

						const timer = window.setTimeout( () => reject( new Error( 'snippet signal timeout' ) ), milliseconds );

						element.addEventListener( 'laravel-snippet:complete', event =>
						{
							window.clearTimeout( timer );

							resolve( ( event as CustomEvent ).detail );
						},
						{ once : true } );

						button.click();
					} ),
					STATUS_POLL_TIMEOUT
					);

					status = detail.status;

					const outputText = detail.output;
					const stderrCount = detail.hasStderr ? 1 : 0;

					if( outputText ) outputPreview = outputText;

					bucket = classify( status, outputText, stderrCount );
				}
				catch
				{
					bucket = 'never-completed';

					try
					{
						status = ( ( await statusEl.textContent() ) ?? '' ).trim();
					}
					catch
					{
					}
				}

				results.push( { page : slug, index : i, bucket, status, outputPreview } );
			}

			flush();
		}
	} );
}

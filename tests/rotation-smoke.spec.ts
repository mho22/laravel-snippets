import { expect, test } from '@playwright/test';


const PROBE_CLASS = 'TestRotationProbe_xyz_321';


test( 'php-wasm runtime rotation smoke', async ( { page } ) =>
{
	test.setTimeout( 3 * 60 * 1000 );

	page.on( 'console', message =>
	{
		const type = message.type();
		const text = message.text();

		if( type === 'error' || type === 'warning' || text.startsWith( '[snippet-worker]' ) ) console.log( `[browser ${type}]`, text );
	} );

	page.on( 'pageerror', error => console.log( '[browser pageerror]', error.message ) );

	await page.goto( '/docs/13.x/queues', { waitUntil : 'domcontentloaded' } );

	await page.waitForFunction(
		() => typeof ( window as { rotateWorker ? : unknown } ).rotateWorker === 'function' && typeof ( window as { runPhp ? : unknown } ).runPhp === 'function',
		null,
		{ timeout : 60_000 }
	);

	const preRotate = await page.evaluate( async probeClass =>
	{
		const runPhp = ( window as unknown as { runPhp : ( code : string ) => Promise<{ stdout : string; stderr : string; exitCode : number; }> } ).runPhp;

		const now = performance.now();

		const r = await runPhp( `class ${probeClass} {}\nvar_dump(class_exists("${probeClass}"));\nvar_dump(file_exists("/bundle/vendor/autoload.php"));` );

		return { ...r, tRun: performance.now() - now };
	},
	PROBE_CLASS
	);

	console.log( 'PRE-ROTATE result:', JSON.stringify( preRotate, null, 2 ) );

	const preBools = preRotate.stdout.match( /bool\((true|false)\)/g ) ?? [];

	expect( preRotate.exitCode, 'pre-rotate snippet should succeed' ).toBe( 0 );
	expect( preBools, 'expected class_exists + file_exists bools' ).toHaveLength( 2 );
	expect( preBools[ 0 ] ).toBe( 'bool(true)' );
	expect( preBools[ 1 ] ).toBe( 'bool(true)' );

	const timings = await page.evaluate( async () =>
	{
		return await ( window as unknown as { rotateWorker: () => Promise<{ tCreate : number; tSwap : number; tTotal : number; error ? : { message : string }; }> } ).rotateWorker();
	} );

	console.log( 'ROTATION timings:', JSON.stringify( timings, null, 2 ) );

	expect( timings.error, 'rotation should not throw' ).toBeUndefined();

	const postRotate = await page.evaluate( async probeClass =>
	{
		const runPhp = ( window as unknown as { runPhp : ( code : string ) => Promise<{ stdout : string; stderr : string; exitCode : number; }> } ).runPhp;

		const now = performance.now();

		const result = await runPhp( `var_dump(class_exists("${probeClass}"));\nvar_dump(file_exists("/bundle/vendor/autoload.php"));` );

		return { ...result, tRun : performance.now() - now };
	},
	PROBE_CLASS
	);

	console.log( 'POST-ROTATE result:', JSON.stringify( postRotate, null, 2 ) );

	const postBools = postRotate.stdout.match( /bool\((true|false)\)/g ) ?? [];

	expect( postRotate.exitCode, 'post-rotate snippet should succeed' ).toBe( 0 );
	expect( postBools, 'expected class_exists + file_exists bools' ).toHaveLength( 2 );
	expect( postBools[ 0 ], 'class should be GONE after rotation' ).toBe( 'bool(false)' );
	expect( postBools[ 1 ], '/bundle/vendor/autoload.php should survive' ).toBe( 'bool(true)' );

	const overBudget = timings.tCreate >= 300 || timings.tSwap >= 1000 ? ' — OVER BUDGET' : '';

	console.log(
		`\n=== ROTATION SMOKE SUMMARY ===${overBudget}\n` +
			`tCreate : ${timings.tCreate.toFixed( 1 )} ms (budget < 300)\n` +
			`tSwap :   ${timings.tSwap.toFixed( 1 )} ms (budget < 1000)\n` +
			`tTotal :  ${timings.tTotal.toFixed( 1 )} ms\n` +
			`class wiped :        ${postBools[ 0 ] === 'bool(false)' ? 'YES' : 'NO'}\n` +
			`/bundle/ preserved : ${postBools[ 1 ] === 'bool(true)' ? 'YES' : 'NO'}\n` +
			`===============================\n`,
	);
} );

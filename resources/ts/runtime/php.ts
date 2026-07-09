
const ASSET_PREFIX = location.pathname.startsWith( '/laravel-snippets' ) ? '/laravel-snippets' : '';
const WORKER_URL = `${ASSET_PREFIX}/snippet-worker/index.js`;

interface PhpResult
{
	type : 'result';
	id : number;
	stdout : string;
	stderr : string;
	exitCode : number;
	tRun : number;
};

interface TokenReply
{
	type : 'tokens';
	id : number;
	tokens : Array<[string | null, string]> | null;
};

interface FatalReply
{
	type : 'fatal';
	stage : string;
	message : string;
	stack ? : string | null;
	name ? : string | null;
	filename ? : string | null;
	lineno ? : number | null;
	colno ? : number | null;
	error ? : PhpError | null;
};

interface PhpError
{
	message : string;
	stack : string | null;
	name : string | null;
}

interface ReadyReply
{
	type : 'ready';
};

interface ProgressReply
{
	type : 'progress';
	percent : number;
};

interface RotatedReply
{
	type : 'rotated';
	id : number;
	tCreate : number;
	tSwap : number;
	tTotal : number;
	error ? : PhpError;
};

interface RotatedResult
{
	tCreate : number;
	tSwap : number;
	tTotal : number;
	error ? : RotatedReply[ 'error' ];
}



type WorkerReply = | PhpResult | TokenReply | FatalReply | ReadyReply | ProgressReply | RotatedReply;


function describeErrorEvent( ev : Event) : string
{
	const event = ev as ErrorEvent;

	const msg = event.message || ( event.error && ( event.error.message || String( event.error ) ) );

	const where = event.filename ? ` (${event.filename}:${event.lineno ?? '?'})` : '';

	return msg ? `${msg}${where}` : 'no error message';
}


function formatFatal( data : FatalReply ) : Error
{
	const parts : string[] = [ `Snippet worker fatal (${data.stage}): ${data.message}` ];

	if( data.filename ) parts.push( `at ${data.filename}:${data.lineno ?? '?'}:${data.colno ?? '?'}` );

	if( data.error?.message && data.error.message !== data.message ) parts.push( `cause: ${data.error.message}` );

	const error = new Error( parts.join( ' — ' ) );

	if( data.stack ) error.stack = data.stack;

	return error;
}


let workerPromise: Promise<Worker> | null = null;
let workerFatal: Error | null = null;
let workerProgress = 0;
const progressListeners = new Set<(percent: number) => void>();
const pending = new Map<number, (data: WorkerReply) => void>();
let nextId = 0;

function emitProgress( percent : number ) : void
{
	workerProgress = percent;

	for( const callback of progressListeners ) callback( percent );
}

function getWorker() : Promise<Worker>
{
	if( workerFatal ) return Promise.reject( workerFatal );

	if( workerPromise ) return workerPromise;

	workerPromise = new Promise( ( resolve, reject ) =>
	{
		const worker = new Worker( WORKER_URL, { type : 'module' } );

		const fail = ( error : Error ) =>
		{
			workerFatal = error;

			console.error('[snippet-worker] ', error );

			reject(error);
		};

		const onReady = ( event : MessageEvent<WorkerReply> ) =>
		{
			if( event.data.type === 'fatal' )
			{
				fail( formatFatal( event.data ) );

				return;
			}

			if( event.data.type === 'progress' )
			{
				emitProgress( event.data.percent );

				return;
			}

			if( event.data.type !== 'ready' ) return;


			emitProgress( 100 );

			worker.removeEventListener( 'message', onReady );

			worker.addEventListener( 'message', ( event : MessageEvent<WorkerReply> ) =>
			{
				const type = event.data.type;

				if( type === 'fatal' )
				{
					fail( formatFatal( event.data ) );

					return;
				}

				if( type !== 'result' && type !== 'tokens' && type !== 'rotated') return;

				const callback = pending.get( event.data.id );

				if( ! callback ) return;

				pending.delete( event.data.id );

				callback( event.data );
			} );

			resolve( worker );
		};

		worker.addEventListener( 'message', onReady );

		worker.addEventListener( 'error', event => fail( new Error( `Snippet worker error : ${describeErrorEvent( event )}` ) ) );

		worker.addEventListener( 'messageerror', event => fail( new Error(`Snippet worker messageerror : ${describeErrorEvent( event )}` ) ) );
	} );

	return workerPromise;
}


export function prewarmWorker() : void
{
	void getWorker().catch( () => {} );
}


export function onWorkerProgress( callback : ( percent : number ) => void ) : () => void
{
	callback( workerProgress );

	progressListeners.add( callback );

	return () => progressListeners.delete( callback );
}


export async function runPhp( code : string ) : Promise<PhpResult>
{
	const worker = await getWorker();

	const id = ++nextId;

	return new Promise( resolve =>
	{
		pending.set( id, data => resolve( data as PhpResult ) );

		worker.postMessage( { id, code } );
	} );
}

export async function runTokenize( code : string) : Promise<TokenReply>
{
	const worker = await getWorker();

	const id = ++nextId;

	return new Promise( resolve =>
	{
		pending.set( id, data => resolve( data as TokenReply ) );

		worker.postMessage( { id, code, action : 'tokenize' } );
	} );
}


export async function rotateWorker() : Promise<RotatedResult>
{
	const worker = await getWorker();

	const id = ++nextId;

	return new Promise( resolve =>
	{
		pending.set( id, data =>
		{
			const response = data as RotatedReply;

			resolve( {
				tCreate : response.tCreate,
				tSwap : response.tSwap,
				tTotal : response.tTotal,
				error : response.error,
			} );
		} );

		worker.postMessage( { id, action : 'rotate' } );
	} );
}

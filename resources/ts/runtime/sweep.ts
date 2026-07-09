import { router } from '@inertiajs/vue3';
import { rotateWorker, runPhp } from '@/runtime/php';

if( typeof window !== 'undefined' && import.meta.env.SWEEP )
{
	const sweepWindow = window as typeof window & {
		inertiaVisit ? : ( url : string ) => Promise<void>;
		rotateWorker ? : typeof rotateWorker;
		runPhp ? : typeof runPhp;
	};

	sweepWindow.inertiaVisit = ( url : string ) => new Promise<void>( ( resolve, reject ) =>
	{
		router.visit( url, {
			onFinish : () => resolve(),
			onError : errors => reject( new Error( JSON.stringify( errors ) ) )
		} );
	} );

	sweepWindow.rotateWorker = rotateWorker;
	sweepWindow.runPhp = runPhp;
}

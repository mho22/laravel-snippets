import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';


export default defineConfig( {
	define : {
		'import.meta.env.SWEEP' : JSON.stringify( process.env.SWEEP === '1' )
	},
	plugins : [
		laravel( { input : [ 'resources/css/app.css', 'resources/ts/app.ts' ], refresh : true } ),
		tailwindcss(),
		vue()
	],
	resolve : {
		alias : { '@' : '/resources/ts' }
	}
} );

import { defineConfig } from 'vite';

export default defineConfig( {
	base : './',
	publicDir : false,
	assetsInclude : [ /\.dat$/ ],
	resolve : {
		alias : [ { find : /\.wasm$/, replacement : '.wasm?url' } ]
	},
	build : {
		outDir : 'public/snippet-worker',
		modulePreload : false,

		rolldownOptions : {
			input : 'resources/ts/runtime/worker.ts',
			output : {
				entryFileNames : 'index.js'
			},
			external : [
				'worker_threads',
				'@php-wasm/web-5-2',
				'@php-wasm/web-7-4',
				'@php-wasm/web-8-0',
				'@php-wasm/web-8-1',
				'@php-wasm/web-8-2',
				'@php-wasm/web-8-3',
				'@php-wasm/web-8-4'
			]
		}
	}
} );

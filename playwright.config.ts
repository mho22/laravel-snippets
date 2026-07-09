import { defineConfig } from '@playwright/test';
import { tmpdir } from 'node:os';
import { join } from 'node:path';


export default defineConfig( {
	testDir : './tests',
	outputDir : join( tmpdir(), 'snippets-test-results' ),
	timeout : 90 * 60 * 1000,
	fullyParallel : true,
	workers : 2,
	reporter : 'list',
	use : {
		baseURL : 'http://localhost:8000',
		actionTimeout : 30_000,
		navigationTimeout : 60_000,
	},
	webServer : [
		{
			command : 'php artisan serve',
			url : 'http://localhost:8000',
			reuseExistingServer : true,
			timeout : 120_000,
		}
	],
	projects : [
		{
			name : 'chromium',
			use : { browserName : 'chromium' }
		}
	]
} );

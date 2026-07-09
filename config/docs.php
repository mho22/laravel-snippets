<?php

return [

	'css' => env( 'LARAVEL_DOCS_CSS_HREF', '/laravel-docs.css' ),
	'root' => resource_path( 'markdown/13.x' ),
	'version' => '13.x',

	'torchlight' => [

		'cache' => storage_path( 'framework/cache/torchlight' ),
		'theme' => 'olaolu-palenight',
		'token' => env( 'TORCHLIGHT_TOKEN', 'torchlight' )

	]
];

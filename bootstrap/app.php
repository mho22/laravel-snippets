<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;


return Application::configure( basePath : dirname( __DIR__ ) )
	->withRouting( web : base_path( '/routes/web.php' ) )
	->withMiddleware( fn( Middleware $middleware ) => $middleware->web( append : HandleInertiaRequests::class ) )
	->withExceptions()
	->create();

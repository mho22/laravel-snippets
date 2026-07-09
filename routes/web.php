<?php

declare( strict_types = 1 );

use App\Http\Controllers\DocsController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;


Route::get( '/report', [ ReportController::class, 'show' ] )->name( 'report' );
Route::get( '/docs/13.x/{page}', [ DocsController::class, 'show' ] )->where( 'page', '[a-z0-9\-]+' )->name( 'docs' );

Route::fallback( fn() => redirect( '/docs/13.x/installation' ) );

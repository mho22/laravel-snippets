<?php

declare( strict_types = 1 );

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;


final class SyncAssetsCommand extends Command
{
	protected $signature = 'sync:assets';

	protected $description = 'Mirror laravel docs CSS and every asset it references into public directory';


	public function handle() : int
	{
		$cssOut = public_path( 'laravel-docs.css' );
		$assetsOut = public_path( 'laravel-docs-assets' );

		$cssUrl = $this->fetchLaravelDocsCssHref();

		if( $cssUrl === null )
		{
			$this->warn( '[ sync-assets ] no live CSS URL; keeping existing files' );

			return self::SUCCESS;
		}

		$cssRes = Http::get( $cssUrl );

		if( ! $cssRes->successful() )
		{
			$this->warn( "[ sync-assets ] CSS fetch failed (HTTP {$cssRes->status()}); keeping existing files" );

			return self::SUCCESS;
		}


		$rawCss = $cssRes->body();

		$origin = parse_url( $cssUrl, PHP_URL_SCHEME ) . '://' . parse_url( $cssUrl, PHP_URL_HOST );

		preg_match_all( '#/build/assets/([A-Za-z0-9._-]+)#', $rawCss, $matches );

		$downloads = array_values( array_unique( $matches[ 1 ] ) );

		if( count( $downloads ) === 0 ) $this->warn( '[ sync-assets ] no /build/assets/ references found in CSS' );

		File::ensureDirectoryExists( $assetsOut );


		$rewrittenCss = str_replace( '/build/assets/', 'laravel-docs-assets/', $rawCss );

		File::put( $cssOut, $rewrittenCss );

		$kb = number_format( strlen( $rewrittenCss ) / 1024, 1 );

		$this->info( "[ sync-assets ] wrote {$cssOut} ({$kb} KB) from {$cssUrl}" );

		$responses = Http::pool( fn( Pool $pool ) => array_map( fn( string $name ) => $pool->as( $name )->timeout( 120 )->get( "{$origin}/build/assets/{$name}" ), $downloads ) );

		foreach( $downloads as $name )
		{
			$response = $responses[ $name ];

			if( $response instanceof Throwable || ! $response->successful() )
			{
				$reason = $response instanceof Throwable ? $response->getMessage() : "HTTP {$response->status()}";

				$this->warn( "[ sync-assets ] asset failed: {$name} ({$reason})" );

				continue;
			}

			File::put( "{$assetsOut}/{$name}", $response->body() );

			$kb = number_format( strlen( $response->body() ) / 1024, 1 );

			$this->info( "[ sync-assets ] asset: {$name} ({$kb} KB)" );
		}

		return self::SUCCESS;
	}


	private function fetchLaravelDocsCssHref() : string | null
	{
		try
		{
			$response = Http::get( 'https://laravel.com/docs/13.x/collections' );

			if( ! $response->successful() ) throw new RuntimeException( "HTTP {$response->status()}" );

			if( ! preg_match( '#https://laravel\.com/build/assets/app-[A-Za-z0-9_-]+\.css#', $response->body(), $match ) )
			{
				throw new RuntimeException( 'no css href matched' );
			}

			return $match[ 0 ];
		}
		catch( Throwable $error )
		{
			$this->warn( "[ sync-assets ] could not refresh laravel.com CSS href: {$error->getMessage()}" );

			return null;
		}
	}
}

<?php

declare( strict_types = 1 );

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;


final class PageDeployCommand extends Command
{
	protected $signature = 'page:deploy';

	protected $description = 'Build the production assets and write the fully static GitHub Pages site into dist/';

	private const PRELOAD_TAGS =
		'<link rel="preload" as="fetch" href="/laravel-snippets/laravel.zip">' .
		'<link rel="preload" as="worker" href="/laravel-snippets/snippet-worker/index.js">';

	private const ROOT_REDIRECT_HTML = <<<'HTML'
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="utf-8">
			<title>Laravel 13.x docs (snippets)</title>
			<script>location.replace("./docs/13.x/installation/");</script>
			<meta http-equiv="refresh" content="0; url=./docs/13.x/installation/">
			<link rel="canonical" href="./docs/13.x/installation/">
		</head>
		<body></body>
		</html>
		HTML;


	public function handle() : int
	{
		$dist = base_path( 'dist' );

		$this->info( '[ page-deploy ] cleaning dist/' );

		File::deleteDirectory( $dist );
		File::ensureDirectoryExists( $dist );

		$this->info( '[ page-deploy ] running production build' );

		$build = Process::path( base_path() )
					->timeout( 1800 )
					->run( [ 'composer', 'build' ], fn( $type, $output ) => $this->output->write( $output ) );

		if( ! $build->successful() )
		{
			$this->error( "[ page-deploy ] composer build exited with status {$build->exitCode()}" );

			return self::FAILURE;
		}

		if( File::exists( public_path( 'hot' ) ) )
		{
			$this->info( '[ page-deploy ] removing stale public/hot (dev marker)' );

			File::delete( public_path( 'hot' ) );
		}

		config( [ 'docs.css' => '/laravel-snippets/laravel-docs.css' ] );

		$html = str_replace( '"url":"\/report"', '"url":"\/laravel-snippets\/report"', $this->render( '/report' ) );

		File::put( "{$dist}/report.html", $html );

		$this->info( '[ page-deploy ] enumerating docs pages' );

		$slugs = collect( File::files( resource_path( 'markdown/13.x' ) ) )
			->map( fn( $file ) => $file->getFilename() )
			->filter( fn( $name ) => str_ends_with( $name, '.md' ) )
			->map( fn( $name ) => substr( $name, 0, -3 ) )
			->sort()
			->values();

		$this->info( "[ page-deploy ] rendering {$slugs->count()} docs pages" );

		foreach( $slugs as $slug )
		{
			$route = "docs/13.x/{$slug}";

			$this->info( "[ page-deploy ] fetching /{$route}" );

			$html = $this->injectSnippetPreloads( $this->rewriteForGithubPages( $this->render( "/{$route}" ), $route ) );

			File::ensureDirectoryExists( "{$dist}/{$route}" );

			File::put( "{$dist}/{$route}/index.html", $html );
		}

		$this->info( '[ page-deploy ] writing root redirect' );

		File::put( "{$dist}/index.html", self::ROOT_REDIRECT_HTML . "\n" );

		$this->info( '[ page-deploy ] copying public assets' );

		File::copyDirectory( public_path( 'build' ), "{$dist}/build" );
		File::copyDirectory( public_path( 'snippet-worker' ), "{$dist}/snippet-worker" );
		File::copy( public_path( 'laravel.zip' ), "{$dist}/laravel.zip" );
		File::copy( public_path( 'laravel-docs.css' ), "{$dist}/laravel-docs.css" );
		File::copyDirectory( public_path( 'laravel-docs-assets' ), "{$dist}/laravel-docs-assets" );

		$this->info( "[ page-deploy ] done → {$dist}" );

		return self::SUCCESS;
	}


	private function render( string $path ) : string
	{
		$kernel = app( Kernel::class );

		$request = Request::create( $path );

		$response = $kernel->handle( $request );

		if( $response->getStatusCode() !== 200 ) throw new RuntimeException( "Render failed for {$path}: HTTP {$response->getStatusCode()}" );

		$kernel->terminate( $request, $response );

		return $response->getContent();
	}


	private function injectSnippetPreloads( string $html ) : string
	{
		if( ! str_contains( $html, 'data-snippet-id=' ) ) return $html;

		return str_replace( '</head>', self::PRELOAD_TAGS . '</head>', $html );
	}


	private function rewriteForGithubPages( string $html, string $route ) : string
	{
		$separation = '\\\\?/';

		$segments = array_map( fn( $segment ) => preg_quote( $segment, '#' ), explode( '/', $route ) );

		$pattern = '#"url":"' . $separation . implode( $separation, $segments ) . $separation . '?"#';

		$escapedRoute = str_replace( '/', '\/', $route );

		return preg_replace_callback( $pattern, fn() => '"url":"\/laravel-snippets\/' . $escapedRoute . '\/"', $html, 1 );
	}
}

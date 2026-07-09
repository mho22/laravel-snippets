<?php

declare( strict_types = 1 );

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;


final class SyncDocsCommand extends Command
{
	protected $signature = 'sync:docs';

	protected $description = 'Refresh resources/markdown/13.x from the upstream laravel/docs 13.x branch';

	private const UPSTREAM_REPO = 'laravel/docs';

	private const UPSTREAM_BRANCH = '13.x';


	public function handle() : int
	{
		$markdownDir = resource_path( 'markdown/13.x' );

		File::ensureDirectoryExists( $markdownDir );

		$treesUrl = 'https://api.github.com/repos/' . self::UPSTREAM_REPO . '/git/trees/' . self::UPSTREAM_BRANCH;
		$rawBase = 'https://raw.githubusercontent.com/' . self::UPSTREAM_REPO . '/' . self::UPSTREAM_BRANCH;

		$headers = [
			'User-Agent' => 'laravel-snippets-sync-docs',
			'Accept' => 'application/vnd.github+json',
		];

		$token = env( 'GITHUB_TOKEN' );

		if( $token ) $headers[ 'Authorization' ] = "Bearer {$token}";

		$treeRes = Http::withHeaders( $headers )->get( $treesUrl );

		if( ! $treeRes->successful() )
		{
			$this->warn( "[ sync-docs ] trees API failed (HTTP {$treeRes->status()}); keeping existing corpus" );

			return self::SUCCESS;
		}

		$tree = $treeRes->json();

		if( $tree[ 'truncated' ] ?? false )
		{
			$this->warn( '[ sync-docs ] trees API returned truncated=true — partial corpus, aborting' );

			return self::FAILURE;
		}

		$upstreamMd = collect( $tree[ 'tree' ] ?? [] )
			->filter( fn( $e ) => ( $e[ 'type' ] ?? '' ) === 'blob' && str_ends_with( $e[ 'path' ], '.md' ) )
			->pluck( 'path' );

		$localMd = collect( File::files( $markdownDir ) )
			->map( fn( $file ) => $file->getFilename() )
			->filter( fn( $name ) => str_ends_with( $name, '.md' ) );

		$toFetch = $upstreamMd->merge( $localMd )->unique()->sort()->values();

		$responses = [];

		$pending = $toFetch;

		for( $attempt = 1; $attempt <= 3 && $pending->isNotEmpty(); $attempt++ )
		{
			if( $attempt > 1 )
			{
				$this->warn( "[ sync-docs ] retrying {$pending->count()} failed fetches [ attempt {$attempt} ]" );

				sleep( $attempt * 5 );
			}

			$stillPending = collect();

			foreach( $pending->chunk( 20 ) as $chunk )
			{
				$results = Http::pool( fn( Pool $pool ) => $chunk->map( fn( string $name ) => $pool->as( $name )->timeout( 120 )->get( "{$rawBase}/{$name}" ) )->all() );

				foreach( $chunk as $name )
				{
					$response = $results[ $name ];

					if( $response instanceof Throwable || ( ! $response->successful() && $response->status() !== 404 ) )
					{
						$stillPending->push( $name );

						continue;
					}

					$responses[ $name ] = $response;
				}
			}

			$pending = $stillPending->values();
		}

		$updated = 0;
		$added = 0;
		$unchanged = 0;
		$missing = 0;
		$failed = 0;


		foreach( $toFetch as $name )
		{
			$response = $responses[ $name ] ?? null;

			if( $response === null )
			{
				$failed++;

				$this->warn( "[ sync-docs ] fetch failed after retries: {$name}" );

				continue;
			}

			if( $response->status() === 404 )
			{
				$missing++;

				continue;
			}

			$fresh = $response->body();
			$localPath = "{$markdownDir}/{$name}";
			$prior = File::exists( $localPath ) ? file_get_contents( $localPath ) : '';

			if( $prior === $fresh )
			{
				$unchanged++;

				continue;
			}

			File::put( $localPath, $fresh );

			$kb = number_format( strlen( $fresh ) / 1024, 1 );

			if( $prior )
			{
				$updated++;

				$this->info( "[ sync-docs ] updated: {$name} ({$kb} KB)" );
			}
			else
			{
				$added++;

				$this->info( "[ sync-docs ] added: {$name} ({$kb} KB)" );
			}
		}

		$this->info( "[ sync-docs ] {$updated} updated, {$added} added, {$unchanged} unchanged, {$missing} missing-upstream, {$failed} failed (corpus: {$toFetch->count()})" );

		if( $failed > 0 )
		{
			$this->error( "[ sync-docs ] {$failed} pages could not be fetched — refusing to leave a partial corpus" );

			return self::FAILURE;
		}

		return self::SUCCESS;
	}
}

<?php

declare( strict_types = 1 );

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;


final class ReportController
{
	public function show() : Response
	{
		$base = base_path( 'tests' );
		$reportJson = $base . '/report.json';
		$resultsDir = $base . '/results';
		$markdownDir = resource_path( 'markdown/13.x' );

		if( ! File::exists( $reportJson ) && ! File::isDirectory( $resultsDir ) )
		{
			return Inertia::render( 'Report', [
				'available' => false,
				'totals' => [],
				'perPageRows' => [],
				'results' => [],
				'buckets' => $this->buckets(),
				'inputs' => []
			] );
		}

		$results = $this->loadResults( $reportJson, $resultsDir );
		$inputs = $this->loadInputs( $markdownDir );

		return Inertia::render( 'Report', [
			'available' => true,
			'totals' => $this->totals( $results ),
			'perPageRows' => $this->perPageRows( $results ),
			'results' => $results,
			'buckets' => $this->buckets(),
			'inputs' => $inputs
		] );
	}

	private function buckets() : array
	{
		return [
			'ran-ok',
			'ran-with-stderr',
			'ran-exit-nonzero',
			'worker-error',
			'no-output',
			'never-completed',
		];
	}

	private function loadResults( string $reportJson, string $resultsDir ) : array
	{
		$results = [];

		if( File::isDirectory( $resultsDir ) )
		{
			$files = collect( File::files( $resultsDir ) )
						->filter( fn( $file ) => str_ends_with( $file->getFilename(), '.json' ) )
						->sortBy( fn( $file ) => $file->getFilename() );

			foreach( $files as $file )
			{
				$decoded = json_decode( file_get_contents( $file->getPathname() ), true );

				if( is_array( $decoded ) )
				{
					foreach( $decoded as $row ) $results[] = $row;
				}
			}
		}
		elseif( File::exists( $reportJson ) )
		{
			$decoded = json_decode( file_get_contents( $reportJson ), true );

			if( is_array( $decoded ) ) $results = $decoded;
		}

		usort( $results, function( $a, $b )
		{
			$pageCmp = strcmp( ( $a[ 'page' ] ?? '' ), ( $b[ 'page' ] ?? '' ) );

			return $pageCmp !== 0 ? $pageCmp : ( ( $a[ 'index' ] ?? 0 ) - ( $b['index'] ?? 0 ) );
		} );

		return $results;
	}

	private function loadInputs( string $markdownDir ) : array
	{
		$inputs = [];

		if( ! File::isDirectory( $markdownDir ) ) return $inputs;

		foreach( File::files( $markdownDir ) as $file )
		{
			if( ! str_ends_with( $file->getFilename(), '.md' ) ) continue;

			$slug = preg_replace( '/\.md$/', '', $file->getFilename() ) ?? '';

			$text = file_get_contents( $file->getPathname() );

			preg_match_all( '/```php\n([\s\S]*?)\n```/', $text, $matches );

			$inputs[ $slug ] = $matches[ 1 ] ?? [];
		}

		return $inputs;
	}

	private function totals( array $results ) : array
	{
		$totals = array_fill_keys( $this->buckets(), 0 );

		foreach( $results as $r )
		{
			$b = ( $r[ 'bucket' ] ?? '' );

			if( isset( $totals[ $b ] ) ) $totals[$b]++;
		}

		return $totals;
	}

	private function perPageRows( array $results ) : array
	{
		$buckets = $this->buckets();

		$perPage = [];

		foreach( $results as $r )
		{
			$page = ( $r[ 'page' ] ?? '' );

			if( ! isset( $perPage[ $page ] ) ) $perPage[ $page ] = array_fill_keys( $buckets, 0 );

			$b = ( $r[ 'bucket' ] ?? '' );

			if( isset( $perPage[ $page ][ $b ] ) ) $perPage[ $page ][ $b ]++;
		}

		$rows = [];

		foreach( $perPage as $page => $counts )
		{
			$total = array_sum( $counts );

			$rows[] = [ 'page' => $page, ...$counts, 'total' => $total ];
		}

		usort( $rows, fn( $a, $b ) => strcmp( $a[ 'page' ], $b[ 'page' ] ) );

		return $rows;
	}
}

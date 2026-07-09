<?php

declare( strict_types = 1 );

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;


final class ReportMergeCommand extends Command
{
	protected $signature = 'report:merge';

	protected $description = 'Merge the per-page sweep results in tests/results/ into tests/report.json';


	public function handle() : int
	{
		$resultsDir = base_path( 'tests/results' );
		$reportJson = base_path( 'tests/report.json' );


		if( ! File::isDirectory( $resultsDir ) && ! File::exists( $reportJson ) )
		{
			$this->error( "No sweep results found. Expected either {$resultsDir}/*.json (per-page worker output) or {$reportJson} (legacy single-file output)." );

			return self::FAILURE;
		}

		if( File::exists( $reportJson ) )
		{
			$results = json_decode( file_get_contents( $reportJson ), true ) ?? [];
		}

		if( File::isDirectory( $resultsDir ) )
		{
			$results = collect( File::files( $resultsDir ) )
				->filter( fn( $file ) => str_ends_with( $file->getFilename(), '.json' ) )
				->sortBy( fn( $file ) => $file->getFilename() )
				->flatMap( fn( $file ) => json_decode( file_get_contents( $file->getPathname() ), true ) )
				->sortBy( [ [ 'page', 'asc' ], [ 'index', 'asc' ] ] )
				->values()
				->all();

			File::put( $reportJson, json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

			File::deleteDirectory( $resultsDir );
		}

		$this->info( 'Wrote ' . $reportJson . ' [ ' . count( $results ) . ' snippets ]' );

		return self::SUCCESS;
	}
}

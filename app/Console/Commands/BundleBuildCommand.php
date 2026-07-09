<?php

declare( strict_types = 1 );

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;


final class BundleBuildCommand extends Command
{
	protected $signature = 'bundle:build';

	protected $description = 'Composer-install the snippet runtime app in bundle/ and zip it into public/laravel.zip';

	private const EXCLUDES = [
		'artisan',
		'node_modules/*',
		'vendor/aws/aws-sdk-php/src/data/*',
	];


	public function handle() : int
	{
		$bundleDir = base_path( 'bundle' );

		$outZip = public_path( 'laravel.zip' );

		if( ! File::isDirectory( $bundleDir ) )
		{
			$this->error( "[ bundle-build ] missing {$bundleDir}" );

			return self::FAILURE;
		}

		$composer = Process::path( $bundleDir )
			->timeout( 600 )
			->run( [ 'composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--prefer-dist' ], fn( $type, $output ) => $this->output->write( $output ) );

		if( ! $composer->successful() )
		{
			$this->error( "[ bundle-build ] composer install exited with status {$composer->exitCode()}" );

			return self::FAILURE;
		}

		File::delete( $outZip );

		$zip = Process::path( $bundleDir )
			->timeout( 600 )
			->run( [ 'zip', '-r', '-q', $outZip, '.', '-x', ...self::EXCLUDES ], fn( $type, $output ) => $this->output->write( $output ) );

		if( ! $zip->successful() )
		{
			$this->error( "[ bundle-build ] zip exited with status {$zip->exitCode()}" );

			return self::FAILURE;
		}

		$size = number_format( File::size( $outZip ) / 1024 / 1024, 2 );

		$this->info( "[ bundle-build ] wrote {$outZip} [ {$size} MB ]" );

		return self::SUCCESS;
	}
}

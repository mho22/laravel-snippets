<?php

declare( strict_types = 1 );

namespace App\Http\Controllers;

use App\Services\MarkdownService;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


final class DocsController
{
	public function show( MarkdownService $markdown, string $page ) : Response
	{
		$root = config( 'docs.root' );

		$path = $root . DIRECTORY_SEPARATOR . $page . '.md';

		if( ! File::exists( $path ) || ! str_starts_with( realpath( $path ) ?: '', realpath( $root ) ?: '' ) ) throw new NotFoundHttpException();

		$source = file_get_contents( $path );

		$rendered = $markdown->render( $source );

		preg_match( '/^#\s+(.+)$/m', $source, $titleMatch );

		$title = $titleMatch[ 1 ] ?? ucfirst( str_replace( '-', ' ', $page ) );

		return Inertia::render( 'Docs', [
			'title' => $title,
			'slug' => $page,
			'body' => $rendered[ 'body' ],
			'markdown' => $source,
			'snippets' => $rendered[ 'snippets' ],
			'sections' => $this->sections( $root )
		] );
	}


	private function sections( string $root ) : array
	{
		$path = $root . DIRECTORY_SEPARATOR . 'documentation.md';

		if( ! File::exists( $path ) ) return [];

		$sections = [];

		foreach( explode( "\n", file_get_contents( $path ) ) as $line )
		{
			if( preg_match( '/^-\s+##\s+(.+)$/', $line, $match ) )
			{
				$sections[] = [ 'title' => trim( $match[ 1 ] ), 'links' => [] ];

				continue;
			}

			if( $sections !== [] && preg_match( '/^\s+-\s+\[(.+?)\]\(\/docs\/\{\{version\}\}\/([^)]+)\)/', $line, $match ) )
			{
				$sections[ array_key_last( $sections ) ][ 'links' ][] = [ 'label' => $match[ 1 ], 'slug' => $match[ 2 ] ];
			}
		}

		return $sections;
	}
}

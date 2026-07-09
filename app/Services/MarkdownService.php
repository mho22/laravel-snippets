<?php

declare( strict_types=1 );

namespace App\Services;

use App\Renderers\BlockQuoteRenderer;
use App\Renderers\SnippetCodeRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;


final class MarkdownService
{
	private readonly TorchlightService $torchlightService;

	public function __construct( TorchlightService $torchlightService )
	{
		$this->torchlightService = $torchlightService;
	}

	public function render( string $markdown ) : array
	{
		$markdown = str_replace( '{{version}}', config( 'docs.version' ), $markdown );

		$snippetRenderer = new SnippetCodeRenderer();

		$environment = ( new Environment() )
			->addExtension( new CommonMarkCoreExtension() )
			->addExtension( new AttributesExtension() )
			->addRenderer( FencedCode::class, $snippetRenderer )
			->addRenderer( BlockQuote::class, new BlockQuoteRenderer() );

		$document = ( new MarkdownParser( $environment ) )->parse( $markdown );

		$missing = [];

		foreach( $document->iterator() as $node )
		{
			if( ! $node instanceof FencedCode ) continue;

			$language = $node->getInfo() ?: 'text';
			$source = $node->getLiteral();
			$id = $this->torchlightService->id( $language, $source );
			$cached = $this->torchlightService->read( $id );

			if( $cached !== null && ( $cached[ 'id' ] ?? null ) === $id )
			{
				$node->data->set( 'torchlight', $cached );
			}
			else
			{
				$missing[] = [ 'id' => $id, 'language' => $language, 'code' => $source, 'node' => $node ];
			}
		}

		if( $missing !== [] )
		{
			$blocks = $this->torchlightService->fetch( array_map(
				static fn( array $b ) : array => [

					'id' => $b[ 'id' ],
					'language' => $b[ 'language' ],
					'theme' => config( 'docs.torchlight.theme' ),
					'code' => $b['code']

				],
				$missing
			) );

			$byId = [];

			foreach( $blocks as $block )
			{
				if( is_array( $block ) && isset( $block[ 'id' ] ) )
				{
					$byId[ $block[ 'id' ] ] = $block;
				}
			}

			foreach( $missing as $entry )
			{
				$result = $byId[ $entry[ 'id' ] ] ?? null;

				if( $result === null ) continue;

				$this->torchlightService->write( $entry[ 'id' ], $result );

				$entry[ 'node' ]->data->set( 'torchlight', $result );
			}
		}

		$body = ( new HtmlRenderer( $environment ) )->renderDocument( $document )->getContent();

		$body = str_replace( '<a name=', '<a id=', $body );

		return [
			'body' => $body,
			'snippets' => $snippetRenderer->snippets
		];
	}
}

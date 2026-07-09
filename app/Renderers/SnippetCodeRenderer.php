<?php

declare( strict_types = 1 );

namespace App\Renderers;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;


final class SnippetCodeRenderer implements NodeRendererInterface
{
	public array $snippets = [];

	private array $pageImports = [];

	private array $pageAssignments = [];

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ) : string
	{
		assert( $node instanceof FencedCode );

		$language = $node->getInfo() ?: 'text';

		$torchlight = $node->data->get( 'torchlight', null );

		$highlighted = is_array( $torchlight ) && isset( $torchlight[ 'highlighted' ] )	? $torchlight[ 'highlighted' ] : htmlspecialchars( $node->getLiteral(), ENT_QUOTES );

		if( $language !== 'php' )
		{
			$classes = htmlspecialchars( $torchlight[ 'classes' ] ?? '', ENT_QUOTES );
			$styles = htmlspecialchars( $torchlight[ 'styles' ] ?? '', ENT_QUOTES );

			return '<div class="code-block-wrapper"><pre><code data-lang="' . htmlspecialchars( $language, ENT_QUOTES )	. '" class="' . $classes . '" style="' . $styles . '">'	. $highlighted . '</code></pre></div>';
		}

		$literal = $node->getLiteral();

		$ownImports = $this->extractUseStatements( $literal );

		$preambleStatements = array_diff_key( $this->pageImports, $ownImports );

		$shadowed = array_merge( $this->extractDeclaredShortNames( $literal ), $this->extractNonCompoundUseNames( $literal ) );

		if( $shadowed !== [] )
		{
			$shadowedSet = array_flip( $shadowed );

			foreach( $preambleStatements as $key => $value )
			{
				$alias = substr( $key, strpos( $key, ':' ) + 1 );

				if( isset( $shadowedSet[ $alias ] ) ) unset( $preambleStatements[ $key ] );
			}
		}

		$ownAssignments = $this->extractTopLevelAssignments( $literal );
		$referenced = $this->extractReferencedVariableNames( $literal );

		$assignmentPreamble = [];

		foreach( array_keys( $referenced ) as $varName )
		{
			if( isset( $ownAssignments[ $varName ] ) ) continue;

			if( isset( $this->pageAssignments[ $varName ] ) ) $assignmentPreamble[] = $this->pageAssignments[ $varName ];
		}

		$preambleParts = array_merge( array_values( $preambleStatements ), $assignmentPreamble );

		$preamble = $preambleParts === [] ? '' : implode( ' ', $preambleParts );


		$this->pageImports = array_merge( $this->pageImports, $ownImports );
		$this->pageAssignments = array_merge( $this->pageAssignments, $ownAssignments );

		$id = 'snippet-'.count( $this->snippets );

		$this->snippets[ $id ] = [
			'php' => $literal,
			'highlighted' => $highlighted,
			'preamble' => $preamble,
		];

		return '<div class="laravel-snippet contains-code-blocks" data-snippet-id="'.$id.'"></div>';
	}

	private function extractUseStatements( string $source ) : array
	{
		$found = [];

		if( ! preg_match_all( '/^use\s+(function\s+|const\s+)?([\\\\\w\s,{}]+?)\s*;[ \t]*$/m', $source, $matches, PREG_SET_ORDER ) ) return $found;

		foreach( $matches as $match )
		{
			$kind = trim( $match[ 1 ] ?? '' );
			$body = trim( $match[ 2 ] );

			if( preg_match( '/^([\\\\\w]+)\\\\\\{([^}]+)\\}$/', $body, $g ) )
			{
				$names = array_map( 'trim', explode( ',', $g[ 2 ] ) );

				$expanded = [];

				foreach( $names as $name )
				{
					$alias = $this->aliasOf( $name );

					if( $alias === null ) continue;

					$expanded[ $alias ] = $kind.':'.$alias;
				}

				$canonical = 'use ' . ( $kind === '' ? '' : $kind . ' ' ) . $body . ';';

				foreach( array_keys( $expanded ) as $alias ) $found[ $kind.':'.$alias ] = $canonical;

				continue;
			}

			$names = array_map( 'trim', explode( ',', $body ) );

			foreach( $names as $name )
			{
				if( $name === '' ) continue;

				$alias = $this->aliasOf( $name );

				if( $alias === null ) continue;

				$canonical = 'use ' . ($kind === '' ? '' : $kind . ' ' ) . $name . ';';

				$found[ $kind . ':' . $alias ] = $canonical;
			}
		}

		return $found;
	}

	private function extractDeclaredShortNames( string $source ) : array
	{
		if( ! preg_match_all( '/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $matches ) ) return [];

		return array_values( array_unique( $matches[ 1 ] ) );
	}

	private function extractNonCompoundUseNames( string $source ) : array
	{
		if( ! preg_match_all( '/^use\s+(\w+)\s*;[ \t]*$/m', $source, $matches ) ) return [];

		return array_values(array_unique($matches[1]));
	}

	private function extractTopLevelAssignments( string $source ) : array
	{
		$tokens = @token_get_all( '<?php ' . $source );

		if( ! $tokens ) return [];

		$n = count( $tokens );

		$startIdx = 0;

		for( $i = 0; $i < $n; $i++ )
		{
			if( is_array( $tokens[ $i ] ) && $tokens[ $i ][ 0 ] === T_OPEN_TAG )
			{
				$startIdx = $i + 1;

				break;
			}
		}

		$skip = [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ];

		$depth = 0;
		$paren = 0;
		$bracket = 0;
		$stmtStart = $startIdx;
		$stmts = [];

		for( $i = $startIdx; $i < $n; $i++ )
		{
			$t = $tokens[ $i ];

			if( is_array( $t ) ) continue;

			switch( $t )
			{
				case '{' : $depth++; break;
				case '}' : $depth--; break;
				case '(' : $paren++; break;
				case ')' : $paren--; break;
				case '[' : $bracket++; break;
				case ']' : $bracket--; break;
				case ';' : if( $depth === 0 && $paren === 0 && $bracket === 0 ) { $stmts[] = [ $stmtStart, $i ]; $stmtStart = $i + 1; } break;
			}
		}

		$assignments = [];

		foreach( $stmts as [ $start, $end ] )
		{
			$firstIdx = null;

			for( $i = $start; $i < $end; $i++ )
			{
				$t = $tokens[ $i ];

				if( is_array( $t ) && in_array( $t[ 0 ], $skip, true ) ) continue;

				$firstIdx = $i;

				break;
			}

			if( $firstIdx === null ) continue;

			$first = $tokens[ $firstIdx ];

			if( ! is_array( $first ) || $first[ 0 ] !== T_VARIABLE ) continue;

			$nextIdx = null;

			for( $j = $firstIdx + 1; $j < $end; $j++ )
			{
				$t2 = $tokens[ $j ];

				if( is_array( $t2 ) && in_array( $t2[ 0 ], $skip, true ) ) continue;

				$nextIdx = $j;

				break;
			}

			if( $nextIdx === null || $tokens[ $nextIdx ] !== '=' ) continue;

			$stmtText = '';

			for( $k = $start; $k <= $end; $k++ )
			{
				$stmtText .= is_array( $tokens[ $k ] ) ? $tokens[ $k ][ 1 ] : $tokens[ $k ];
			}

			$varName = substr( $first[ 1 ], 1 );

			$assignments[ $varName ] = ltrim( $stmtText );
		}

		return $assignments;
	}

	private function extractReferencedVariableNames( string $source ) : array
	{
		$tokens = @token_get_all( '<?php ' . $source );

		if( ! $tokens ) return [];

		$names = [];

		foreach( $tokens as $t )
		{
			if( is_array( $t ) && $t[ 0 ] === T_VARIABLE && $t[ 1 ] !== '$this' )
			{
				$names[ substr( $t[ 1 ], 1 ) ] = true;
			}
		}

		return $names;
	}

	private function aliasOf( string $entry ) : string | null
	{
		if( preg_match( '/^([\\\\\w]+)\s+as\s+(\w+)$/i', $entry, $m ) ) return $m[ 2 ];

		if( ! str_contains( $entry, '\\' ) ) return null;

		$parts = explode( '\\', $entry );

		$last = end( $parts );

		return is_string( $last ) && $last !== '' ? $last : null;
	}
}

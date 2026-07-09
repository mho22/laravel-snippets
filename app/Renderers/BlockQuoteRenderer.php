<?php

declare( strict_types = 1 );

namespace App\Renderers;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;


final class BlockQuoteRenderer implements NodeRendererInterface
{
	private const CALLOUTS = [
		'NOTE' => [ 'color' => '#8D54C5', 'svg' => '<svg width="16" height="21" viewBox="0 0 16 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 20H11M5.5 14H10.5M15 8C15 4.13401 11.866 1 8 1C4.13401 1 1 4.13401 1 8C1 10.7924 2.63505 13.2029 5 14.3264V17H11V14.3264C13.3649 13.2029 15 10.7924 15 8Z" stroke="#FDFDFC" stroke-width="1.5" stroke-linecap="square"/></svg>' ],
		'WARNING' => [ 'color' => '#F53003', 'svg' => '<svg width="2" height="18" viewBox="0 0 2 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 17V16.99M1 1V13" stroke="#FDFDFC" stroke-width="2" stroke-linecap="square"/></svg>' ]
	];

	public function render( Node $node, ChildNodeRendererInterface $childRenderer) : string
	{
		assert( $node instanceof BlockQuote );

		$callout = $this->extractCallout( $node );

		if( $callout === null ) return '<blockquote>' . $childRenderer->renderNodes( $node->children() ) . '</blockquote>';

		[ $type, $paragraph ] = $callout;

		$color = self::CALLOUTS[ $type ][ 'color' ];

		$svg = self::CALLOUTS[ $type ][ 'svg' ];

		$body = $childRenderer->renderNodes( $paragraph->children() );

		return '<div class="flex flex-col p-3 mb-10 space-y-4 text-base leading-normal border rounded-md lg:px-4 lg:flex-row lg:space-y-0 lg:space-x-4 border-sand-light-5 callout dark:border-sand-dark-5 dark:text-sand-light-3 text-sand-dark-3">'
			. '<div class="w-8 h-8 p-2 lg:my-1.5 rounded-xs flex items-center justify-center shrink-0 bg-[' . $color . ']">'
			. $svg
			. '</div><p class="callout text-pretty">' . $body . '</p></div>';
	}

	private function extractCallout( BlockQuote $node ) : array | null
	{
		$paragraph = $node->firstChild();

		if( ! $paragraph instanceof Paragraph ) return null;


		$first = $paragraph->firstChild();

		if( ! $first instanceof Text ) return null;

		if( ! preg_match( '/^\[!(NOTE|WARNING)\]$/', $first->getLiteral(), $m ) ) return null;

		$next = $first->next();

		$first->detach();

		if( $next instanceof Newline )
		{
			$next->detach();
		}

		return [ $m[ 1 ], $paragraph ];
	}
}

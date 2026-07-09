type Token = [ string | null, string ];


const ansiColors : Record<string, string> = {
	'1;38;5;38' : '#82AAFF',
	'1;38;5;113' : '#C3E88D',
	'1;38;5;208' : '#F78C6C',
	'38;5;38' : '#82AAFF',
	'38;5;113' : '#C3E88D',
	'38;5;170' : '#C792EA',
	'38;5;208' : '#F78C6C',
	'38;5;247' : '#676E95',
};

const paleNightTheme = {
	variable : '#BEC5D4',
	string : '#C3E88D',
	number : '#F78C6C',
	comment : '#676E95',
	keyword : '#C792EA',
	function : '#82AAFF',
	arrow : '#89DDFF',
	literal : '#FF5874',
	default : '#BFC7D5',
} as const;

const PHP_KEYWORD_TOKENS = new Set( [
	'T_FUNCTION', 'T_RETURN', 'T_IF', 'T_ELSE', 'T_ELSEIF', 'T_FOR',
	'T_FOREACH', 'T_WHILE', 'T_DO', 'T_SWITCH', 'T_CASE', 'T_DEFAULT',
	'T_BREAK', 'T_CONTINUE', 'T_CLASS', 'T_INTERFACE', 'T_TRAIT',
	'T_EXTENDS', 'T_IMPLEMENTS', 'T_NEW', 'T_USE', 'T_NAMESPACE', 'T_TRY',
	'T_CATCH', 'T_FINALLY', 'T_THROW', 'T_ECHO', 'T_PRINT', 'T_VAR',
	'T_PUBLIC', 'T_PROTECTED', 'T_PRIVATE', 'T_STATIC', 'T_ABSTRACT',
	'T_FINAL', 'T_CONST', 'T_AS', 'T_INSTANCEOF', 'T_FN', 'T_YIELD',
	'T_REQUIRE', 'T_REQUIRE_ONCE', 'T_INCLUDE', 'T_INCLUDE_ONCE',
	'T_ARRAY', 'T_LIST', 'T_ISSET', 'T_UNSET', 'T_EMPTY', 'T_GLOBAL',
	'T_ENUM', 'T_MATCH', 'T_READONLY', 'T_CALLABLE',
] );

const PHP_ARROW_TOKENS = new Set( [
	'T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_ARROW',
	'T_PAAMAYIM_NEKUDOTAYIM', 'T_NS_SEPARATOR',
	'T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO', 'T_CLOSE_TAG',
] );

const PHP_LITERAL_IDENTIFIERS = new Set( [
	'null', 'true', 'false', 'NULL', 'TRUE', 'FALSE',
] );

const PHP_PUNCT_OPERATORS = '=+-*/<>!.&|^~%?:';


export function escapeHtml( string : string ) : string
{
	return string.replace( /[&<>]/g, code => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ code ] as string ) );
}


export function ansiToHtml( text : string ) : string
{
	const out : string[] = [];

	let cursor = 0;
	let openSpan = false;
	const regex = /\x1b\[([0-9;]*)m/g;

	let match : RegExpExecArray | null;

	while( ( match = regex.exec( text ) ) !== null )
	{
		if( match.index > cursor ) out.push( escapeHtml( text.slice( cursor, match.index ) ) );

		const code = match[1];

		if( openSpan )
		{
			out.push('</span>');

			openSpan = false;
		}

		if( code !== '' && code !== '0' && code !== '39' )
		{
			const color = ansiColors[ code ] || '#BFC7D5';

			out.push( `<span style="color:${color}">` );

			openSpan = true;
		}

		cursor = match.index + match[0].length;
	}

	if( cursor < text.length ) out.push( escapeHtml( text.slice( cursor ) ) );

	if( openSpan ) out.push( '</span>' );

	return out.join( '' );
}


function colorForToken( token : Token, next : Token | undefined ) : string
{
	const [ name, text ] = token;

	if( name === 'T_VARIABLE' ) return paleNightTheme.variable;
	if( name === 'T_LNUMBER' || name === 'T_DNUMBER' ) return paleNightTheme.number;
	if( name === 'T_CONSTANT_ENCAPSED_STRING' ) return paleNightTheme.string;
	if( name === 'T_ENCAPSED_AND_WHITESPACE' ) return paleNightTheme.string;
	if( name === 'T_COMMENT' || name === 'T_DOC_COMMENT' ) return paleNightTheme.comment;
	if( name && PHP_KEYWORD_TOKENS.has( name ) ) return paleNightTheme.keyword;
	if( name && PHP_ARROW_TOKENS.has( name ) ) return paleNightTheme.arrow;

	if( name === 'T_STRING' )
	{
		if( PHP_LITERAL_IDENTIFIERS.has( text ) ) return paleNightTheme.literal;

		if( next && next[ 1 ] === '(' ) return paleNightTheme.function;

		return paleNightTheme.default;
	}

	if( name === null && text.length === 1 && PHP_PUNCT_OPERATORS.includes( text ) )
	{
		return paleNightTheme.keyword;
	}

	return paleNightTheme.default;
}


export function buildHighlightedHtml( tokens : Token[] ) : string
{
	const lines: string[] = [''];

	for( let i = 0; i < tokens.length; i++ )
	{
		const token = tokens[ i ];

		const color = colorForToken( token, tokens[ i + 1 ] );

		const segments = token[ 1 ].split( '\n' );

		for( let k = 0; k < segments.length; k++ )
		{
			if( k > 0 ) lines.push('');

			if( segments[ k ] ) lines[ lines.length - 1 ] += `<span style="color:${color};">${escapeHtml(segments[k])}</span>`;
		}
	}

	const numWidth = String( lines.length ).length;

	return lines.map( ( value, index ) =>
	{
		const num = String( index + 1 ).padStart( numWidth, ' ' );

		return `<div class="line"><span style="color:#4c5374; text-align:right; -webkit-user-select:none; user-select:none;" class="line-number" contenteditable="false">${num}</span>${value}</div>`;
	} )
	.join('');
}


export function getCaretLineCol( root : HTMLElement ) : [ number, number ] | null
{
	const selection = window.getSelection();

	if( ! selection || ! selection.rangeCount ) return null;

	const range = selection.getRangeAt( 0 );

	if( ! root.contains( range.startContainer ) ) return null;

	const lines = Array.from( root.querySelectorAll<HTMLElement>( ':scope > .line' ) );

	for( let i = 0; i < lines.length; i++ )
	{
		if( ! lines[ i ].contains( range.startContainer ) ) continue;

		let col = 0;

		const walker = document.createTreeWalker( lines[ i ], NodeFilter.SHOW_TEXT );

		let node : Node | null;

		while( ( node = walker.nextNode() ) )
		{
			if( ( node.parentElement as HTMLElement | null )?.classList.contains( 'line-number' ) ) continue;

			if( node === range.startContainer ) return [ i, col + range.startOffset ];

			col += node.textContent?.length ?? 0;
		}

		return [ i, col ];
	}

	return null;
}


export function setCaretLineCol( root : HTMLElement, [ lineIdx, col ] : [ number, number ] ) : void
{
	const lines = root.querySelectorAll<HTMLElement>( ':scope > .line' );

	if( lines.length === 0 ) return;

	const line = lines[ Math.min( lineIdx, lines.length - 1 ) ];

	let remaining = col;

	const walker = document.createTreeWalker( line, NodeFilter.SHOW_TEXT );

	let node : Node | null;

	while( ( node = walker.nextNode() ) )
	{
		if( ( node.parentElement as HTMLElement | null )?.classList.contains( 'line-number' ) ) continue;

		const len = node.textContent?.length ?? 0;

		if( remaining <= len )
		{
			const range = document.createRange();

			range.setStart( node, remaining );

			range.collapse( true );

			const selection = window.getSelection();

			selection?.removeAllRanges();

			selection?.addRange(range);

			return;
		}

		remaining -= len;
	}

	const range = document.createRange();

	range.selectNodeContents( line );

	range.collapse( false );

	const selection = window.getSelection();

	selection?.removeAllRanges();

	selection?.addRange( range );
}

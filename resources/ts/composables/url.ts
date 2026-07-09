
const BASE = typeof location !== 'undefined' && location.pathname.startsWith( '/laravel-snippets' ) ? '/laravel-snippets' : '';

export function docsUrl( slug : string ) : string
{
	return BASE ? `${BASE}/docs/13.x/${slug}/` : `/docs/13.x/${slug}`;
}

export function homeUrl() : string
{
	return BASE ? `${BASE}/` : '/';
}

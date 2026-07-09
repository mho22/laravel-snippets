<!DOCTYPE html>

<html lang="en">

	<head>

		<meta charset="utf-8">

		<meta name="viewport" content="width=device-width,initial-scale=1">

		<link rel="preconnect" href="https://fonts.bunny.net">

		<link rel="stylesheet" href="https://fonts.bunny.net/css?family=ibm-plex-mono:500|merriweather:400&display=swap">

		<link rel="stylesheet" href="{{ config('docs.css') }}">

		@vite( [ 'resources/css/app.css', 'resources/ts/app.ts' ] )

		<script>( function() { try { var p=localStorage.getItem( 'laravel-theme' ), d = p ==='dark' || ( p !== 'light' && matchMedia( '(prefers-color-scheme: dark)' ).matches ); document.documentElement.classList.toggle( 'dark', d ); document.documentElement.setAttribute( 'data-theme', d ? 'dark':'light' ) } catch(e ) {} } )()</script>

		@inertiaHead

	</head>

	<body class="bg-white font-sans text-neutral-900 antialiased dark:text-neutral-100 dark:bg-neutral-900">

		@inertia

	</body>

</html>

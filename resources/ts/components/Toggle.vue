<script setup lang="ts">

import { onBeforeUnmount, onMounted, ref } from 'vue';


type Preference = 'system' | 'light' | 'dark';


const paths : Record<Preference, string> = {
	system : 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
	light : 'M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z',
	dark : 'M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z',
};

const nextLabel : Record<Preference, string> = {
	system : 'Switch to light mode',
	light : 'Switch to dark mode',
	dark : 'Switch to system mode',
};

const nextPreference : Record<Preference, Preference> = { system : 'light', light : 'dark', dark : 'system' };


const preference = ref<Preference>( readPreference() );

const onSystemChange = () => { if( preference.value === 'system' ) apply( 'system' ); };



function readPreference() : Preference
{
	try
	{
		const raw = localStorage.getItem( 'laravel-theme' );

		return raw === 'light' || raw === 'dark' || raw === 'system' ? raw : 'system';

	}
	catch
	{
		return 'system';
	}
}


function computeDark( preference : Preference ) : boolean
{
	if( preference === 'dark' ) return true;
	if( preference === 'light' ) return false;

	try
	{
		return matchMedia( '(prefers-color-scheme: dark)' ).matches;
	}
	catch
	{
		return false;
	}
}


function apply( next : Preference ) : void
{
	const dark = computeDark( next );

	const root = document.documentElement;

	root.setAttribute( 'data-theme', dark ? 'dark' : 'light' );

	root.classList.toggle( 'dark', dark );
}


function cycle() : void
{
	preference.value = nextPreference[ preference.value ];

	try
	{
		localStorage.setItem( 'laravel-theme', preference.value );
	}
	catch
	{
	}

	apply( preference.value );
}


onMounted( () =>
{
	try
	{
		matchMedia( '(prefers-color-scheme: dark)' ).addEventListener( 'change', onSystemChange );
	}
	catch
	{
	}
});

onBeforeUnmount( () =>
{
	try
	{
		matchMedia( '(prefers-color-scheme: dark)' ).removeEventListener( 'change', onSystemChange );
	}
	catch
	{
	}
});

</script>

<template>

	<button
		type="button"
		v-bind:title="nextLabel[ preference ]"
		class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
		v-on:click="cycle"
	>
		<svg
			xmlns="http://www.w3.org/2000/svg"
			fill="none"
			viewBox="0 0 24 24"
			stroke-width="1.5"
			stroke="currentColor"
			aria-hidden="true"
			class="size-5"
		>

			<path stroke-linecap="round" stroke-linejoin="round" :d="paths[ preference ]" />

		</svg>

		<span class="sr-only">{{ nextLabel[ preference ] }}</span>

	</button>

</template>

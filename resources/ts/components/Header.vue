<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Logotype from '@/components/Logotype.vue';
import Toggle from '@/components/Toggle.vue';


interface HeaderProps
{
	title ? : string;
}


defineProps<HeaderProps>();


const shrunk = ref( false );


function onScroll() : void
{
	if( ! shrunk.value && window.scrollY > 128 )
	{
		shrunk.value = true;
	}

	if( shrunk.value && window.scrollY < 112 )
	{
		shrunk.value = false;
	}
}


onMounted( () =>
{
	window.addEventListener( 'scroll', onScroll, { passive : true } );

	onScroll();
} );

onBeforeUnmount( () => window.removeEventListener( 'scroll', onScroll ) );

</script>

<template>
	<nav class="sticky-nav docs-nav sticky top-0 z-99 mx-auto h-fit max-w-350 border-b-0! border-l border-neutral-200! bg-white px-4 py-8 transition-transform duration-300 xl:px-16 dark:border-neutral-700! dark:bg-neutral-900" :class="shrunk ? '-translate-y-4 border-b border-neutral-100 pb-4 dark:border-sand-dark-6' : ''">

		<div class="relative grid h-full grid-cols-12 items-center gap-4 overflow-hidden lg:gap-6 xl:gap-x-10">

			<ul class="col-span-3 flex items-start space-x-8 font-medium">

				<li class="mr-18 w-20 text-laravel-red transition-transform duration-300 ease-in-out">

					<Logotype />

				</li>

			</ul>

			<template v-if="title">

				<div class="col-span-6 flex items-center justify-center">

					<h1 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">{{ title }}</h1>

				</div>

				<div class="col-span-3 flex items-center justify-end gap-4">

					<Toggle />

				</div>

			</template>

			<template v-else>

				<div class="absolute -top-1 right-9 flex cursor-not-allowed items-center rounded-xs p-2 text-sand-light-9-alpha-45 lg:relative lg:top-auto lg:right-auto lg:col-span-6 lg:w-full lg:bg-sand-light-3 lg:hover:bg-sand-light-4 dark:text-sand-dark-11 lg:dark:bg-sand-dark-5 lg:dark:hover:bg-sand-dark-4">

					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 lg:mr-2 lg:h-5 lg:w-5">

						<path d="M15.8333 15.8333L13.0523 13.0524M13.0523 13.0524C13.9943 12.1104 14.5769 10.8092 14.5769 9.3718C14.5769 6.49708 12.2465 4.16667 9.37176 4.16667C6.49704 4.16667 4.16663 6.49708 4.16663 9.3718C4.16663 12.2465 6.49704 14.5769 9.37176 14.5769C10.8091 14.5769 12.1104 13.9943 13.0523 13.0524Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />

					</svg>

					<div class="grow">

						<div class="absolute inset-0 bg-transparent text-base text-sand-light-9-alpha-45/70 *:absolute *:inset-0 lg:*:px-9 lg:*:py-1.5 dark:text-sand-dark-10">

							<span class="hidden lg:block">Search disabled in demo</span>

						</div>

					</div>

					<span class="mr-1.5 ml-2 hidden shrink-0 text-sm font-medium text-sand-light-9 lg:block dark:text-sand-dark-10">⌘K</span>

				</div>

				<div class="col-span-9 flex items-center justify-end gap-4 lg:col-span-3 dark:text-sand-dark-12">

					<span class="hidden bg-sand-light-1 px-3 py-1.5 text-sm whitespace-nowrap lg:block dark:bg-sand-dark-1">Version 13.x</span>

					<span class="bg-sand-light-1 px-3 py-1.5 text-sm whitespace-nowrap lg:hidden dark:bg-sand-dark-1">v13.x</span>

					<div class="hidden lg:block">

						<Toggle />

					</div>

					<button class="ml-13 cursor-pointer lg:hidden">

						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-sand-light-9">

							<path d="M2.75 7.25H21.25M2.75 16.75H21.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" />

						</svg>

					</button>

				</div>

			</template>

		</div>

	</nav>

</template>

<script setup lang="ts">

import { computed, ref } from 'vue';


interface RightProps
{
	body : string;
	markdown : string;
}

interface Child
{
	id : string;
	text : string;
}


interface Entry extends Child
{
	children : Child[];
}


const props = defineProps<RightProps>();

const entries = computed<Entry[]>( () =>
{
	if( typeof DOMParser === 'undefined' ) return [];

	const doc = new DOMParser().parseFromString( props.body, 'text/html' );

	const entries : Entry[] = [];

	for( const heading of doc.body.querySelectorAll( 'h2, h3' ) )
	{
		const previous = heading.previousElementSibling;

		const anchor = previous instanceof HTMLAnchorElement ? previous : previous?.querySelector( ':scope > a[id]' );

		const id = anchor?.id ?? '';

		if( ! id ) continue;

		const text = heading.textContent?.trim() ?? '';

		if( heading.tagName === 'H2' )
		{
			entries.push( { id, text, children: [] } );

		}
		else if( entries.length > 0 )
		{
			entries[ entries.length - 1 ].children.push( { id, text } );
		}
	}
	return entries;
} );

const LINK_CLASS = 'inline-block border-l-[3px] border-transparent text-[13px] text-sand-light-11 hover:border-sand-dark-4/25 hover:text-sand-light-12 dark:text-sand-dark-11 dark:hover:border-sand-light-4/25 dark:hover:text-sand-dark-12';


const copied = ref( false );

let copyTimeout : number | undefined;


function copy() : void
{
	navigator.clipboard.writeText( props.markdown );

	copied.value = true;

	window.clearTimeout( copyTimeout );

	copyTimeout = window.setTimeout( () => copied.value = false, 3000 );
}

</script>

<template>

	<div class="relative col-span-12 hidden lg:col-span-9 lg:col-start-4 lg:pb-10 xl:col-span-3 xl:col-start-auto xl:block">

		<div class="relative pl-10 lg:sticky lg:top-28">

			<div class="flex flex-col items-start gap-4 text-xs">

				<button type="button" class="group inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-sand-light-11 not-disabled:hover:text-sand-light-12 dark:text-sand-dark-11 dark:not-disabled:hover:text-sand-dark-12" v-on:click="copy">

					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block size-4 text-sand-light-9 group-not-disabled:group-hover:text-sand-light-12 dark:text-sand-dark-11 dark:group-not-disabled:group-hover:text-sand-dark-12">

						<path d="M5.83301 5.83325V1.83325H14.1663V10.1666H10.1663" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" />

						<path d="M1.83301 5.83325H10.1663V14.1666H1.83301V5.83325Z" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" />

					</svg>

					<span>{{ copied ? 'Copied' : 'Copy as markdown' }}</span>

				</button>

			</div>

			<div>

				<h3 class="mt-9 mb-3 flex items-center gap-2 text-sm font-medium text-sand-light-11 dark:text-sand-dark-11">

					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block size-4 text-sand-light-9 dark:text-sand-dark-11">

						<path d="M1.83301 7.99992H14.1663M1.83301 3.83325H14.1663M1.83301 12.1666H7.66634" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" />

					</svg>

					On this page

				</h3>

				<div class="clean-scrollbar max-h-[calc(100vh-140px)] space-y-10 overflow-y-auto pb-10">

					<div class="border-l border-sand-light-5 pr-4 dark:border-sand-dark-5">

						<ul class="space-y-1">

							<li v-for="entry in entries" v-bind:key="entry.id" class="mt-1.5 py-0.5 first:mt-0">

								<a v-bind:href="`#${entry.id}`" v-bind:class="[ LINK_CLASS, 'pl-4' ]">{{ entry.text }}</a>

								<ul v-if="entry.children.length" class="mt-1.5">

									<li v-for="child in entry.children" v-bind:key="child.id" class="py-0.5">

										<a v-bind:href="`#${child.id}`" v-bind:class="[ LINK_CLASS, 'pl-8' ]">{{ child.text }}</a>

									</li>

								</ul>

							</li>

						</ul>

					</div>

				</div>

			</div>

		</div>

	</div>

</template>

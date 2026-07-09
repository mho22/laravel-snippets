<script setup lang="ts">

import { ref } from 'vue';
import { docsUrl } from '@/composables/url';


interface LeftLink
{
	slug : string;
	label : string;
}

interface LeftSection
{
	title : string;
	links : LeftLink[];
}

interface LeftProps
{
	current : string;
	sections : LeftSection[];
}

const props = defineProps<LeftProps>();


const opened = ref( new Set( props.sections.filter( section => section.links.some( link => link.slug === props.current ) ).map( slug => slug.title ) ) );


function toggle( title : string) : void
{
	const next = new Set( opened.value );

	if( next.has( title ) )
	{
		next.delete( title );
	}
	else
	{
		next.add(title);
	}

	opened.value = next;
}

</script>

<template>

	<aside class="relative col-span-3 lg:pb-6">

		<div class="sticky top-22 bottom-0 left-0 z-20 hidden lg:block">

			<div class="sticky-side-nav clean-scrollbar relative -ml-16 flex max-h-screen flex-1 flex-col overflow-auto pl-16">

				<nav id="indexed-nav" class="hidden lg:block">

					<div class="docs_sidebar">

						<ul>

							<li v-for="section in sections" v-bind:key="section.title" v-bind:class="{ 'sub--on' : opened.has( section.title ) }" >

								<h2 v-on:click="toggle( section.title )">{{ section.title }}</h2>

								<ul>

									<li v-for="link in section.links" v-bind:key="link.slug" v-bind:class="{ active: link.slug === current }">

										<a v-bind:href="docsUrl(link.slug)">{{ link.label }}</a>

									</li>

								</ul>

							</li>

							<li>

								<h2>

									<a href="https://api.laravel.com/docs/13.x">API Documentation</a>

								</h2

							></li>

						</ul>

					</div>

				</nav>

			</div>

		</div>

	</aside>

</template>

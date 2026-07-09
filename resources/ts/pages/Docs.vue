<script setup lang="ts">

import { createApp, h, onBeforeUnmount, onMounted, ref, watch, type App } from 'vue';
import { Head } from '@inertiajs/vue3';
import Header from '@/components/Header.vue';
import Left from '@/components/Left.vue';
import Right from '@/components/Right.vue';
import Snippet from '@/components/Snippet.vue';


interface DocsProps
{
	title : string;
	slug : string;
	body : string;
	markdown : string;
	snippets : Record<string, SnippetData>;
	sections : Section[];
}

interface SnippetData
{
	php : string;
	highlighted : string;
	preamble : string;
}

interface Section
{
	title : string;
	links : Link[];
}

interface Link
{
	slug : string;
	label : string;
}


let mounted : App[] = [];


function hydrate()
{
	teardown();

	const root = bodyRef.value;

	if( ! root ) return;

	const placeholders = root.querySelectorAll<HTMLDivElement>( '[data-snippet-id]' );

	placeholders.forEach( element =>
	{
		const id = element.dataset.snippetId;

		if( ! id ) return;

		const snippet = props.snippets[ id ];

		if( ! snippet ) return;

		const app = createApp( { render : () => h( Snippet, { php : snippet.php, highlighted : snippet.highlighted, preamble : snippet.preamble } ) } );

		app.mount( element );

		mounted.push( app );
	});
}


function teardown()
{
	mounted.forEach( app => app.unmount() );

	mounted = [];
}




const props = defineProps<DocsProps>();


const bodyRef = ref<HTMLDivElement | null>( null );


watch( () => [ props.body, props.snippets ], hydrate, { deep : false } );


onMounted( hydrate );

onBeforeUnmount( teardown );

</script>

<template>

	<Head v-bind:title="`${title} — Laravel 13.x docs`" />

	<Header />

	<div class="mx-auto max-w-350 border-l border-neutral-200 dark:border-neutral-700">

		<div class="px-4 xl:px-16">

			<div id="docsScreen" class="grid grid-cols-12 gap-4 px-6 pt-10 lg:gap-6 lg:px-0 xl:gap-x-10">

				<Left v-bind:current="slug" v-bind:sections="sections" />

				<section class="col-span-12 lg:col-span-9 xl:col-span-6">

					<section class="docs_main max-w-prose">

						<div ref="bodyRef" id="main-content" class="contains-code-blocks" v-html="body" />

					</section>

				</section>

				<Right v-bind:body="body" v-bind:markdown="markdown" />

			</div>

		</div>

	</div>

</template>

<script setup lang="ts">

import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Header from '@/components/Header.vue';


type Bucket = | 'ran-ok' | 'ran-with-stderr' | 'ran-exit-nonzero' | 'worker-error' | 'no-output' | 'never-completed';

type BucketCounts = Record<Bucket, number>;

type SortKey = 'page' | 'total' | Bucket;


interface SnippetResult
{
	page : string;
	index : number;
	bucket : Bucket;
	status : string;
	outputPreview ? : string;
}

interface PerPageRow extends BucketCounts
{
	page : string;
	total : number;
}

interface ReportProps
{
	available : boolean;
	totals : BucketCounts;
	perPageRows : PerPageRow[];
	results : SnippetResult[];
	buckets : Bucket[];
	inputs : Record<string, string[]>;
}




const maxRows = 1000;

const sectionTitle = 'mb-3 text-xs font-semibold tracking-[0.08em] uppercase text-report-text-muted';
const tableHeader = 'border-b border-report-border bg-report-bg-elevated px-3 py-2 text-left text-[11px] font-semibold tracking-[0.04em] uppercase select-none';
const tableHeaderSortable = 'cursor-pointer hover:text-report-text-strong';
const tableData = 'border-b border-report-border px-3 py-2 align-top';
const tableDataNumber = 'text-right tabular-nums';
const input = 'min-w-[180px] rounded-md border border-report-border bg-report-bg px-2.5 py-1.5 text-[13px] text-report-text focus:border-report-accent focus:outline-none';
const pre = 'm-0 max-h-[360px] overflow-auto rounded border border-report-border bg-report-pre-bg px-3 py-2.5 font-mono text-xs leading-[1.45] whitespace-pre-wrap wrap-break-word text-report-pre-text';
const detailLabel = 'mb-1 text-[11px] tracking-[0.08em] uppercase text-report-text-muted';
const pill = 'inline-block rounded px-2 py-0.5 text-[11px] leading-[1.4] font-semibold';


const bucketTextClass : Record<Bucket, string> = {
	'ran-ok' : 'text-report-ok',
	'ran-with-stderr' : 'text-report-warn',
	'ran-exit-nonzero' : 'text-report-fail',
	'worker-error' : 'text-report-fail',
	'no-output' : 'text-report-muted',
	'never-completed' : 'text-report-fail',
};

const bucketPillClass: Record<Bucket, string> = {
	'ran-ok' : `${pill} bg-report-pill-ok-bg text-report-pill-ok-text`,
	'ran-with-stderr' : `${pill} bg-report-pill-warn-bg text-report-pill-warn-text`,
	'ran-exit-nonzero' : `${pill} bg-report-pill-fail-bg text-report-pill-fail-text`,
	'worker-error' : `${pill} bg-report-pill-fail-strong-bg text-report-pill-fail-strong-text`,
	'no-output' : `${pill} bg-report-pill-muted-bg text-report-pill-muted-text`,
	'never-completed' : `${pill} bg-report-pill-fail-strong-bg text-report-pill-fail-strong-text`,
};




const props = defineProps<ReportProps>();


const total = computed( () => props.results.length );

const visibleResults = computed(() => filteredResults.value.slice( 0, maxRows ) );

const sortedPerPage = computed( () =>
{
	const key = sortKey.value;
	const directory = sortDir.value;
	const copy = [ ...props.perPageRows ];

	copy.sort( ( a, b ) =>
	{
		const aValue = rowValue( a, key );
		const bValue = rowValue( b, key );

		const comparison = typeof aValue === 'string' && typeof bValue === 'string' ? aValue.localeCompare( bValue ) : Number( aValue ) - Number( bValue );

		return directory === 'asc' ? comparison : -comparison;
	} );

	return copy;
} );

const filteredResults = computed( () =>
{
	const lowerText = text.value.toLowerCase();

	return props.results.filter( result =>
	{
		if( selectedBucket.value !== 'all' && result.bucket !== selectedBucket.value ) return false;
		if( page.value !== 'all' && result.page !== page.value ) return false;

		if( lowerText )
		{
			const input = getInput( result.page, result.index );
			const blob = ( result.outputPreview || '' ) + ' ' + result.status + ' ' + result.page + ' ' + input;

			if( ! blob.toLowerCase().includes( lowerText ) ) return false;
		}

		return true;
	});
});



const selectedBucket = ref<'all' | Bucket>( 'all' );
const page = ref<'all' | string>( 'all' );
const text = ref( '' );
const sortKey = ref<SortKey>( 'page' );
const sortDir = ref<'asc' | 'desc'>( 'asc' );
const expanded = ref( new Set<string>() );



function getInput( prop : string, index : number ) : string
{
	const array = props.inputs[ prop ];

	if( ! array || index >= array.length ) return '';

	return array[ index ];
}


function stripAnsi( string : string) : string
{
	return string.replace( /\x1b\[[0-9;]*m/g, '' ).replace( /<\/?[a-z][^>]*>/gi, '' ).replace( /&[a-z]+;|&#\d+;/gi, '' );
}


function summaryLine( string ? : string ) : string
{
	if( ! string ) return '';

	const stripped = stripAnsi( string );

	for( const line of stripped.split( '\n' ) )
	{
		const trimmed = line.trim();

		if( trimmed ) return trimmed;
	}

	return '';
}


function rowValue( row : PerPageRow, key : SortKey ) : string | number
{
	return ( row as unknown as Record<string, string | number> )[ key ];
}


function setSort( key : SortKey ) : void
{
	if( sortKey.value === key )
	{
		sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
	}
	else
	{
		sortKey.value = key;
		sortDir.value = key === 'page' ? 'asc' : 'desc';
	}
}


function ariaSortFor( key : SortKey ) : 'ascending' | 'descending' | undefined
{
	if( sortKey.value !== key ) return undefined;

	return sortDir.value === 'asc' ? 'ascending' : 'descending';
}


function sortArrow( key : SortKey ) : string
{
	if( sortKey.value !== key ) return '';

	return sortDir.value === 'asc' ? '▲' : '▼';
}


function tableHeaderClasses( key : SortKey ) : string[]
{
	return [ tableHeader, tableHeaderSortable, sortKey.value === key ? 'text-report-text-strong' : 'text-report-text-muted' ];
}


function clickBucketCard( key : 'all' | Bucket )
{
	selectedBucket.value = key;
}


function clickPageLink( link : string )
{
	page.value = link;

	scrollToSnippets();
}


function scrollToSnippets()
{
	const element = document.getElementById( 'snippet-table-anchor' );

	element?.scrollIntoView( { behavior : 'smooth', block : 'start' } );
}


function toggleRow( key : string )
{
	const next = new Set( expanded.value );

	if( next.has( key ) )
	{
		next.delete( key );
	}
	else
	{
		next.add( key );
	}

	expanded.value = next;
}


function truncate( string : string, number : number) : string
{
	return string.length > number ? string.slice( 0, number ) + '…' : string;
}


function percentage( value : number ) : string
{
	if( ! total.value ) return '0.0%';

	return ( ( value / total.value ) * 100 ).toFixed( 1 ) + '%';
}

</script>

<template>

	<Head title="Snippets report — Laravel 13.x" />

	<Header title="Snippets report" />

	<div class="mx-auto max-w-350 border-l border-neutral-200 dark:border-neutral-700">

		<div class="px-4 xl:px-16">

			<main class="py-10">

				<div v-if="! available" class="rounded-lg border border-report-border bg-report-bg-elevated p-6 text-sm text-report-text">

					<p class="m-0 font-semibold">No sweep results yet.</p>

					<p class="m-0 mt-1">
						Run the Playwright sweep and then

						<code class="rounded bg-[rgba(120,120,120,0.18)] px-1 text-[13px]">php artisan report:merge</code> to populate

						<code class="rounded bg-[rgba(120,120,120,0.18)] px-1 text-[13px]">tests/report.json</code>.

					</p>

				</div>

				<div v-else class="text-report-text">

					<section class="mb-10">

						<h2 v-bind:class="sectionTitle">Bucket totals</h2>

						<div class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-3">

							<button
								type="button"
								class="cursor-pointer appearance-none rounded-lg border bg-report-bg px-4 py-3.5 text-left text-report-text transition-colors hover:border-report-accent"
								v-bind:class="selectedBucket === 'all' ? 'border-report-accent bg-report-bg-elevated' : 'border-report-border'"
								v-on:click="clickBucketCard( 'all' )"
							>

								<div class="mb-1 text-xs text-report-text-muted">Total</div>

								<div class="text-2xl leading-[1.1] font-semibold text-report-text-strong">{{ total }}</div>

							</button>

							<button
								v-for="bucket in buckets"
								v-bind:key="bucket"
								type="button"
								class="cursor-pointer appearance-none rounded-lg border bg-report-bg px-4 py-3.5 text-left text-report-text transition-colors hover:border-report-accent"
								v-bind:class="selectedBucket === bucket ? 'border-report-accent bg-report-bg-elevated' : 'border-report-border'"
								v-on:click="clickBucketCard( bucket )"
							>

								<div class="mb-1 text-xs" v-bind:class="bucketTextClass[ bucket ]">{{ bucket }}</div>

								<div class="text-2xl leading-[1.1] font-semibold" v-bind:class="bucketTextClass[ bucket ]">{{ totals[ bucket ] }}</div>

								<div class="mt-0.5 text-[11px] text-report-text-muted">{{ percentage(totals[ bucket ]) }}</div>

							</button>

						</div>

					</section>

					<section class="mb-10">

						<h2 v-bind:class="sectionTitle">Per-page rollup</h2>

						<div class="overflow-x-auto rounded-lg border border-report-border bg-report-bg">

							<table class="w-full border-collapse text-[13px]">

								<thead>

									<tr>

										<th v-bind:class="tableHeaderClasses( 'page' )" v-bind:aria-sort="ariaSortFor('page')" v-on:click="setSort( 'page' )">

											page

											<span class="ml-1 text-report-accent">{{ sortArrow('page') }}</span>

										</th>

										<th
											v-for="bucket in buckets"
											v-bind:key="bucket"
											v-bind:class="[ tableHeaderClasses( bucket ), 'text-right' ]"
											v-bind:aria-sort="ariaSortFor( bucket )"
											v-on:click="setSort( bucket )"
										>

											{{ bucket }}

											<span class="ml-1 text-report-accent">{{ sortArrow( bucket ) }}</span>

										</th>

										<th v-bind:class="[ tableHeaderClasses( 'total' ), 'text-right' ]" v-bind:aria-sort="ariaSortFor( 'total' )" v-on:click="setSort( 'total' )">

											total

											<span class="ml-1 text-report-accent">{{ sortArrow('total') }}</span>

										</th>

									</tr>

								</thead>

								<tbody class="[&>tr:last-child>td]:border-b-0">

									<tr v-for="row in sortedPerPage" v-bind:key="row.page" class="hover:bg-report-bg-hover">

										<td v-bind:class="tableData">

											<a href="#snippet-table-anchor" class="text-report-accent hover:underline" v-on:click.prevent="clickPageLink( row.page )" >{{ row.page }}</a>

										</td>

										<td v-for="bucket in buckets" v-bind:key="bucket" v-bind:class="[ tableData, tableDataNumber, bucketTextClass[ bucket ]]">{{ row[ bucket ] }}</td>

										<td v-bind:class="[ tableData, tableDataNumber, 'font-medium text-report-text-strong' ]">{{ row.total }}</td>

									</tr>

								</tbody>

							</table>

						</div>

					</section>

					<section class="mb-10" id="snippet-table-anchor">

						<h2 v-bind:class="sectionTitle">All snippets</h2>

						<div class="mb-3 flex flex-wrap gap-3">

							<select v-model="selectedBucket" v-bind:class="input">

								<option value="all">All buckets</option>

								<option v-for="bucket in buckets" v-bind:key="bucket" v-bind:value="bucket">{{ bucket }}</option>

							</select>

							<select v-model="page" v-bind:class="input">

								<option value="all">All pages</option>

								<option v-for="row in perPageRows" v-bind:key="row.page" v-bind:value="row.page">{{ row.page }}</option>

							</select>

							<input v-model="text" type="search" placeholder="search output…" v-bind:class="[ input, 'min-w-55 flex-1' ]"	/>

						</div>

						<div class="py-1.5 text-[13px] text-report-text-muted">Showing {{ filteredResults.length.toLocaleString() }} of {{ total.toLocaleString() }} snippets</div>

						<div class="overflow-x-auto rounded-lg border border-report-border bg-report-bg">

							<table class="w-full border-collapse text-[13px]">

								<thead>

									<tr>

										<th v-bind:class="[ tableHeader, 'w-7 pr-1 pl-2 text-report-text-muted' ]"></th>
										<th v-bind:class="[ tableHeader, 'text-report-text-muted' ]">Page</th>
										<th v-bind:class="[ tableHeader, 'text-right text-report-text-muted' ]">#</th>
										<th v-bind:class="[ tableHeader, 'text-report-text-muted' ]">Bucket</th>
										<th v-bind:class="[ tableHeader, 'text-report-text-muted' ]">Status</th>
										<th v-bind:class="[ tableHeader, 'text-report-text-muted' ]">Output (first line)</th>

									</tr>

								</thead>

								<tbody class="[&>tr:last-child>td]:border-b-0">

									<template v-for="result in visibleResults" v-bind:key="`${result.page}-${result.index}`">

										<tr
											class="cursor-pointer"
											v-bind:class="expanded.has( `${result.page}-${result.index}` ) ? 'bg-report-bg-elevated' : 'hover:bg-report-bg-hover'"
											v-on:click="toggleRow( `${result.page}-${result.index}` )"
										>

											<td v-bind:class="[ tableData, 'w-7 pr-1 pl-2 text-center text-report-accent']">{{ expanded.has( `${result.page}-${result.index}` ) ? '▾' : '▸' }}</td>
											<td v-bind:class="tableData">{{ result.page }}</td>
											<td v-bind:class="[ tableData, tableDataNumber]">{{ result.index }}</td>
											<td v-bind:class="tableData"><span :class="bucketPillClass[ result.bucket ]">{{ result.bucket }}</span></td>
											<td v-bind:class="tableData">{{ result.status || 'empty' }}</td>
											<td v-bind:class="[ tableData, 'max-w-150 overflow-hidden font-mono text-xs text-ellipsis whitespace-nowrap text-report-text-muted' ]">{{ truncate( summaryLine( result.outputPreview ), 100 ) || 'no output' }}</td>

										</tr>

										<tr v-if="expanded.has(`${result.page}-${result.index}`)" class="bg-report-bg-elevated">

											<td colspan="6" class="border-b border-report-border px-3 pt-0 pb-3.5">

												<div class="mt-2.5">

													<div v-bind:class="detailLabel">Input</div>

													<pre v-bind:class="[ pre, 'border-l-[3px] border-l-report-accent' ]">{{ getInput( result.page, result.index ) || '(no source)' }}</pre>

												</div>

												<div class="mt-2.5">

													<div v-bind:class="detailLabel">Output</div>

													<pre v-bind:class="[ pre, 'border-l-[3px] border-l-report-warn' ]">{{ result.outputPreview || '(no output)' }}</pre>

												</div>

											</td>

										</tr>

									</template>

									<tr v-if="filteredResults.length > maxRows">

										<td colspan="6" class="border-b border-report-border p-3 text-center text-report-text-muted">

											Showing first {{ maxRows.toLocaleString() }} rows; refine filters to see more.

										</td>

									</tr>

								</tbody>

							</table>

						</div>

					</section>

				</div>

			</main>

		</div>

	</div>

</template>

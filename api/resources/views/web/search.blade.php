@extends('layouts.app')

@section('content')
<x-breadcrumb :items="$breadcrumbs" />

<div class="mx-auto max-w-7xl px-16 pb-40 lg:px-24">
    <div class="flex flex-col gap-24 lg:flex-row">
        <aside class="w-full shrink-0 lg:w-[256px] space-y-16">
            @include('web.partials.search-category-tree')
            @include('web.partials.filters', ['action' => route('web.search')])

            <div class="mt-24">
                <x-banner-slot position="sidebar" />
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <div class="mb-16 flex flex-wrap items-center justify-between gap-8">
                <div>
                    <h1 class="text-xl font-bold">{{ $query ? "\"{$query}\"" : 'All listings' }}</h1>
                    <p class="text-sm text-sokoni-black/50">{{ __('site.results_count', ['count' => $products->total()]) }}</p>
                </div>
                @include('web.partials.sort-select')
            </div>

            @include('web.partials.active-filter-chips')

            {{-- "Shops first when the query looks like a name" (tester feedback C2)
                 — a real shop match is itself the signal the query names a shop;
                 no match means it doesn't, and products lead exactly as before. --}}
            @if ($shopsFirst)
                @include('web.partials.search-shops-section')
            @endif

            @if ($products->isEmpty() && $sponsored->isEmpty() && $shops->isEmpty())
                @include('web.partials.empty-results')
            @elseif ($products->isNotEmpty() || $sponsored->isNotEmpty())
                @if ($shops->isNotEmpty())
                    <h2 class="mb-12 text-sm font-semibold text-sokoni-black/60">{{ __('site.search_products_heading') }}</h2>
                @endif
                <div class="grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:gap-24">
                    @foreach ($sponsored as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                {{ $products->links() }}
            @endif

            @unless ($shopsFirst)
                @include('web.partials.search-shops-section')
            @endunless
        </div>
    </div>
</div>
@endsection

@push('head')
    @if ($products->previousPageUrl())
        <link rel="prev" href="{{ $products->previousPageUrl() }}">
    @endif
    @if ($products->nextPageUrl())
        <link rel="next" href="{{ $products->nextPageUrl() }}">
    @endif
@endpush

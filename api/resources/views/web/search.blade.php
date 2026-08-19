@extends('layouts.app')

@section('content')
<x-breadcrumb :items="$breadcrumbs" />

<div class="mx-auto max-w-7xl px-16 pb-40 lg:px-24">
    <div class="flex flex-col gap-24 lg:flex-row">
        <aside class="w-full shrink-0 lg:w-64">
            @include('web.partials.filters', ['action' => route('web.search')])
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

            @if ($products->isEmpty() && $sponsored->isEmpty())
                @include('web.partials.empty-results')
            @else
                <div class="grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($sponsored as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                {{ $products->links() }}
            @endif
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

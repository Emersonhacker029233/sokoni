@extends('layouts.app')

@section('content')
<x-breadcrumb :items="$breadcrumbs" />

<div class="mx-auto max-w-7xl px-16 pb-40 lg:px-24">
    <div class="flex flex-col gap-24 lg:flex-row">
        {{-- Sidebar: subcategories + filters (Jiji reference) --}}
        <aside class="w-full shrink-0 lg:w-64">
            @if ($children->isNotEmpty())
                <div class="mb-24">
                    <h2 class="mb-8 text-sm font-semibold text-sokoni-black/50">{{ $parentCategory->name(app()->getLocale()) }}</h2>
                    <ul class="space-y-4">
                        @foreach ($children as $child)
                            <li>
                                <a href="{{ route('web.category', [app(\App\Services\Catalog\CategoryCatalogService::class)->slug($parentCategory), app(\App\Services\Catalog\CategoryCatalogService::class)->slug($child)]) }}"
                                   class="flex justify-between rounded-chip px-8 py-6 text-sm {{ $category->id === $child->id ? 'bg-sokoni-surface-alt font-semibold' : 'text-sokoni-black/70 hover:bg-sokoni-surface-alt' }}">
                                    <span>{{ $child->name(app()->getLocale()) }}</span>
                                    <span class="text-sokoni-black/40">{{ $child->products_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('web.partials.filters', ['action' => url()->current()])
        </aside>

        <div class="min-w-0 flex-1">
            <div class="mb-16 flex flex-wrap items-center justify-between gap-8">
                <div>
                    <h1 class="text-xl font-bold">{{ $category->name(app()->getLocale()) }}</h1>
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

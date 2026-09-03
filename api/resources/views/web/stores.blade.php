@extends('layouts.app')

@section('content')
<x-breadcrumb :items="[['label' => 'Sokoni', 'url' => route('web.home')], ['label' => __('site.nav_stores'), 'url' => null]]" />

<div class="container-sokoni pb-40">
    <h1 class="text-h2">{{ __('site.nav_stores') }}</h1>
    <p class="text-body mt-8">{{ __('site.stores_description') }}</p>

    <form action="{{ route('web.stores') }}" method="get" class="mt-24 flex flex-col gap-12 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="flex-1 sm:min-w-[200px]">
            <label for="stores-q" class="text-sm font-medium">{{ __('site.stores_search_label') }}</label>
            <input type="text" id="stores-q" name="q" value="{{ $query }}" placeholder="{{ __('site.stores_search_placeholder') }}" class="input-field mt-4">
        </div>

        <div>
            <label for="stores-region" class="text-sm font-medium">{{ __('site.filter_region') }}</label>
            <select id="stores-region" name="region" class="input-field mt-4">
                <option value="">{{ __('site.search_region_all') }}</option>
                @foreach ($regions as $slug => $name)
                    <option value="{{ $slug }}" @selected(request('region') === $slug)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="stores-category" class="text-sm font-medium">{{ __('site.seller_category') }}</label>
            <select id="stores-category" name="category_id" class="input-field mt-4">
                <option value="">{{ __('site.seller_category_placeholder') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name(app()->getLocale()) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="stores-sort" class="text-sm font-medium">{{ __('site.sort_label') }}</label>
            <select id="stores-sort" name="sort" onchange="this.form.submit()" class="input-field mt-4">
                <option value="rating" @selected($sort === 'rating')>{{ __('site.stores_sort_rating') }}</option>
                <option value="newest" @selected($sort === 'newest')>{{ __('site.sort_newest') }}</option>
                <option value="listings" @selected($sort === 'listings')>{{ __('site.stores_sort_listings') }}</option>
            </select>
        </div>

        <button type="submit" class="btn-primary">{{ __('site.filter_apply') }}</button>
    </form>

    <p class="text-sm text-sokoni-black/50 mt-16">{{ __('site.results_count', ['count' => $stores->total()]) }}</p>

    @if ($stores->isEmpty())
        @include('web.partials.empty-results')
    @else
        <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 lg:gap-24">
            @foreach ($stores as $store)
                <x-shop-card :shop="$store" />
            @endforeach
        </div>

        {{ $stores->links() }}
    @endif
</div>
@endsection

@extends('layouts.app')

@section('content')
    {{-- Hero search — the dominant element, per the Jiji reference. --}}
    <section class="border-b border-sokoni-outline bg-sokoni-surface-alt/40">
        <div class="mx-auto max-w-4xl px-16 py-40 text-center lg:py-56">
            <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('site.home_hero_title') }}</h1>
            <p class="mx-auto mt-12 max-w-xl text-sokoni-black/60">{{ __('site.home_hero_subtitle') }}</p>

            <form action="{{ route('web.search') }}" method="get" class="mx-auto mt-24 flex max-w-2xl flex-col gap-8 rounded-card border border-sokoni-outline bg-white p-8 shadow-sm sm:flex-row sm:items-stretch">
                <input
                    type="text"
                    name="q"
                    placeholder="{{ __('site.search_placeholder') }}"
                    class="min-w-0 flex-1 rounded-chip border-none px-16 py-14 text-base focus:outline-none focus:ring-0"
                    autofocus
                >
                <select name="region" class="rounded-chip border border-sokoni-outline bg-sokoni-surface-alt px-12 py-12 text-sm">
                    <option value="">{{ __('site.search_region_all') }}</option>
                    @foreach (\App\Support\TanzaniaRegions::options() as $slug => $name)
                        <option value="{{ $slug }}">{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-primary px-24 py-14 text-base">{{ __('site.search_submit') }}</button>
            </form>
        </div>
    </section>

    {{-- Category grid --}}
    <section class="mx-auto max-w-7xl px-16 py-32 lg:px-24">
        <h2 class="text-xl font-bold">{{ __('site.home_categories') }}</h2>
        <div class="mt-16 grid grid-cols-2 gap-12 sm:grid-cols-3 md:grid-cols-5">
            @foreach ($categories as $category)
                <a href="{{ route('web.category', app(\App\Services\Catalog\CategoryCatalogService::class)->slug($category)) }}" class="card flex flex-col items-center gap-8 p-16 text-center transition hover:shadow-md">
                    <span class="flex h-48 w-48 items-center justify-center rounded-full bg-sokoni-yellow/20 text-2xl">
                        {{ $category->icon ?? '🛍️' }}
                    </span>
                    <span class="text-sm font-semibold">{{ $category->name(app()->getLocale()) }}</span>
                    <span class="text-xs text-sokoni-black/40">{{ $category->products_count }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Near you --}}
    @if ($nearYou->isNotEmpty())
        <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
            <h2 class="text-xl font-bold">{{ __('site.home_near_you') }}</h2>
            <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($nearYou as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Live offers --}}
    @if ($offers->isNotEmpty())
        <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">{{ __('site.home_offers') }}</h2>
                <a href="{{ route('web.search') }}?sponsored=1" class="text-sm font-medium text-sokoni-black/60 hover:underline">{{ __('site.home_offers_see_all') }}</a>
            </div>
            <div class="mt-16 flex gap-16 overflow-x-auto pb-8">
                @foreach ($offers as $offer)
                    <div class="w-56 shrink-0 sm:w-64">
                        <a href="{{ route('web.product', ['product' => $offer->product_id, 'slug' => \Illuminate\Support\Str::slug($offer->product->title)]) }}" class="card block overflow-hidden">
                            <div class="aspect-square bg-sokoni-surface-alt">
                                @php($media = $offer->product->media->first())
                                @if ($media)
                                    <img src="{{ $media->card_path ?? $media->path }}" alt="{{ $offer->product->title }}" loading="lazy" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <div class="p-12">
                                <p class="line-clamp-1 text-sm font-medium">{{ $offer->product->title }}</p>
                                <div class="mt-4 flex items-baseline gap-6">
                                    <span class="font-bold text-sokoni-danger">{{ \App\Support\Money::format($offer->discountedPrice()) }}</span>
                                    <span class="text-xs text-sokoni-black/40 line-through">{{ \App\Support\Money::format((int) $offer->price_snapshot) }}</span>
                                </div>
                                <p x-data="sokoniCountdown('{{ $offer->ends_at->toIso8601String() }}')" x-init="tick()" x-text="label" class="mt-4 text-xs font-medium text-sokoni-black/50"></p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured verified shops --}}
    @if ($featuredShops->isNotEmpty())
        <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
            <h2 class="text-xl font-bold">{{ __('site.home_featured_shops') }}</h2>
            <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-4 md:grid-cols-8">
                @foreach ($featuredShops as $shop)
                    <x-shop-card :shop="$shop" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Latest listings --}}
    <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
        <h2 class="text-xl font-bold">{{ __('site.home_latest') }}</h2>
        <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($latest as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>

    {{-- App download banner — this page's second job. --}}
    <section id="app-download" class="mx-16 my-32 rounded-card bg-sokoni-black px-24 py-32 text-center text-white lg:mx-auto lg:max-w-7xl">
        <h2 class="text-2xl font-bold">{{ __('site.home_app_banner_title') }}</h2>
        <p class="mx-auto mt-8 max-w-md text-white/70">{{ __('site.home_app_banner_body') }}</p>
        <a href="https://play.google.com/store/apps" class="btn-primary mt-20 inline-flex">{{ __('site.home_app_banner_cta') }}</a>
    </section>
@endsection

@push('head')
    <script>
        function sokoniCountdown(endsAtIso) {
            return {
                label: '',
                tick() {
                    const diffMs = new Date(endsAtIso) - new Date();
                    if (diffMs <= 0) { this.label = 'Ended'; return; }
                    const h = Math.floor(diffMs / 3600000);
                    const m = Math.floor((diffMs % 3600000) / 60000);
                    this.label = h > 24 ? Math.floor(h / 24) + 'd left' : h + 'h ' + m + 'm left';
                    setTimeout(() => this.tick(), 60000);
                },
            };
        }
    </script>
@endpush

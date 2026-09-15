@extends('layouts.app')

@section('content')
    {{-- Hero search — the dominant element, per the Jiji reference. Background
         is a subtle brand-yellow geometric lattice (public/images/hero-pattern.svg,
         under 300 bytes — no real market photography exists in this repo, and
         tester feedback item 6 explicitly calls for this fallback over a stock
         photo that doesn't fit) plus a dark gradient, so the search card is what
         actually stands out rather than the backdrop competing for attention. --}}
    {{-- B1 (client feedback): a "search_background" banner renders behind
         this whole band — artwork visible at the left/right edges, a
         scrim over the centre (where the search card sits) instead of a
         flat overlay across the whole image, so it never fights the
         input's own legibility. Falls back to exactly today's plain
         pattern+gradient backdrop when no such banner is active.

         Part 5 (client feedback): with more than one active banner in
         this position, rotate between them — crossfade, 7s per banner,
         pause on hover, respect prefers-reduced-motion (first image
         only, no rotation at all). With exactly one, this renders
         identically to before (no Alpine, no transition markup) — "with
         a single banner, behave exactly as now." Every banner that will
         ever be shown during this page view gets its impression counted
         right here, server-side, at render time — rotation happens
         entirely client-side afterwards with no further requests, so
         this is the one place "shown" can be measured at all (see
         Banner::recordImpression()'s own docblock on why this is a
         render-time count, not client-side pixel tracking). --}}
    @php($searchBgBanners = \App\Models\Banner::live()->forPosition('search_background')->get())
    <section
        class="relative overflow-hidden border-b border-sokoni-outline bg-sokoni-black"
        @if ($searchBgBanners->count() > 1)
            {{-- Attached to the whole section, not just the image stack —
                 the search form itself sits on top of it (its own opaque
                 card blocks pointer events from reaching anything
                 beneath), so "pause on hover" needs to fire from
                 wherever the visitor's mouse actually is, including
                 while they're reading/typing in the form. --}}
            x-data="{
                active: 0,
                total: {{ $searchBgBanners->count() }},
                paused: false,
                reduceMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                init() {
                    if (this.reduceMotion) return;
                    setInterval(() => {
                        if (! this.paused) this.active = (this.active + 1) % this.total;
                    }, 7000);
                },
            }"
            @mouseenter="paused = true"
            @mouseleave="paused = false"
        @endif
    >
        @if ($searchBgBanners->count() > 1)
            @foreach ($searchBgBanners as $banner)
                @php($banner->recordImpression())
            @endforeach
            <div class="absolute inset-0">
                @foreach ($searchBgBanners as $index => $banner)
                    <a
                        href="{{ route('web.banners.click', $banner) }}"
                        class="absolute inset-0 block transition-opacity duration-1000 ease-in-out"
                        aria-label="{{ $banner->title }}"
                        :class="active === {{ $index }} ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                        {{ $index > 0 ? 'aria-hidden="true"' : '' }}
                    >
                        <img src="{{ $banner->image_path }}" alt="" class="h-full w-full object-cover">
                    </a>
                @endforeach
            </div>
            {{-- One shared scrim above every rotating layer — the search
                 field must stay readable over whichever banner is
                 currently showing, and this dims all of them identically
                 without needing to duplicate it per image. --}}
            <div
                class="absolute inset-0"
                style="background: linear-gradient(to right, transparent 0%, rgba(10,10,10,.88) 32%, rgba(10,10,10,.88) 68%, transparent 100%)"
                aria-hidden="true"
            ></div>
        @elseif ($searchBgBanners->isNotEmpty())
            @php($searchBg = $searchBgBanners->first())
            @php($searchBg->recordImpression())
            <a href="{{ route('web.banners.click', $searchBg) }}" class="absolute inset-0 block" aria-label="{{ $searchBg->title }}">
                <img src="{{ $searchBg->image_path }}" alt="" class="h-full w-full object-cover">
            </a>
            <div
                class="absolute inset-0"
                style="background: linear-gradient(to right, transparent 0%, rgba(10,10,10,.88) 32%, rgba(10,10,10,.88) 68%, transparent 100%)"
                aria-hidden="true"
            ></div>
        @else
            <div class="absolute inset-0" style="background-image: url('{{ asset('images/hero-pattern.svg') }}'); background-repeat: repeat;" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-sokoni-black/60 via-sokoni-black/80 to-sokoni-black" aria-hidden="true"></div>
        @endif
        <div class="relative mx-auto max-w-4xl px-16 py-40 text-center lg:py-56">
            <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{{ __('site.home_hero_title') }}</h1>
            <p class="mx-auto mt-12 max-w-xl text-white/70">{{ __('site.home_hero_subtitle') }}</p>

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

    <x-banner-slot position="home_hero" />

    {{-- Category tiles, noon.com style (Part 3, client feedback) — replaces
         the old icon-chip grid entirely. Exactly the nav bar's own
         category list, same set and same order: megaMenuTree() is the
         same cached call partials.header already uses for the mega menu,
         not HomeController's old product-count-filtered list, which the
         client was explicit must NOT be used here. Each tile is a
         photograph the admin uploaded (CategoryForm's new `image` field);
         a category with none set falls back to the existing inline SVG
         icon set on a brand-yellow tile rather than an empty box. B2
         (client feedback): a narrow side slot next to the row,
         desktop-only, collapsing to nothing on its own when no banner is
         active. --}}
    @php($homeCategoryTiles = app(\App\Services\Catalog\CategoryCatalogService::class)->megaMenuTree())
    <section class="mx-auto max-w-7xl px-16 py-32 lg:px-24">
        <h2 class="text-h2 fade-in-section">{{ __('site.home_categories') }}</h2>
        <div class="mt-16 flex gap-16 lg:gap-24">
            {{-- Horizontally scrollable with no visible scrollbar on
                 mobile, a plain grid from sm up — same mechanism as the
                 "Near you" row below. --}}
            <div class="no-scrollbar flex flex-1 gap-16 overflow-x-auto pb-8 sm:grid sm:grid-cols-4 sm:overflow-visible sm:gap-24 md:grid-cols-6 lg:grid-cols-8">
                @foreach ($homeCategoryTiles as $category)
                    <a
                        href="{{ route('web.category', $category['slug']) }}"
                        class="flex w-96 shrink-0 flex-col items-center gap-8 text-center sm:w-auto"
                    >
                        @if ($category['image'])
                            <img
                                src="{{ $category['image'] }}"
                                alt=""
                                loading="lazy"
                                class="h-80 w-80 rounded-full object-cover"
                            >
                        @else
                            <span class="flex h-80 w-80 items-center justify-center rounded-full bg-sokoni-yellow text-sokoni-black">
                                <x-category-icon :icon="$category['icon']" />
                            </span>
                        @endif
                        <span class="w-full truncate text-sm font-semibold">
                            {{ app()->getLocale() === 'sw' ? $category['name_sw'] : $category['name_en'] }}
                        </span>
                    </a>
                @endforeach
            </div>
            <x-banner-slot position="category_strip_side" variant="side" class="hidden lg:block" />
        </div>
    </section>

    {{-- "In Focus" advertising band, noon.com style (Part 4, client
         feedback). Built on the same Banner model/position mechanism as
         every other ad slot, but genuinely different from
         <x-banner-slot>: this shows every active banner in the position
         at once, as its own poster (2-4, per the design brief — capped
         at 4 so an admin activating more than that doesn't turn "a row of
         posters" into an unbounded strip), not just one. Collapses to
         nothing — not even the heading — when none are active. --}}
    @php($inFocusBanners = \App\Models\Banner::live()->forPosition('in_focus')->limit(4)->get())
    @if ($inFocusBanners->isNotEmpty())
        <section class="mx-auto max-w-7xl px-16 py-32 lg:px-24">
            <h2 class="text-h2 fade-in-section">{{ __('site.home_in_focus') }}</h2>
            <div class="no-scrollbar mt-16 flex gap-16 overflow-x-auto pb-8 lg:gap-24">
                @foreach ($inFocusBanners as $banner)
                    @php($banner->recordImpression())
                    <a
                        href="{{ route('web.banners.click', $banner) }}"
                        class="block w-[220px] shrink-0 overflow-hidden rounded-card border border-sokoni-outline transition hover:shadow-md"
                    >
                        <img src="{{ $banner->image_path }}" alt="{{ $banner->title }}" loading="lazy" class="aspect-[3/4] w-full object-cover">
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Near you. B3 (client feedback): a side slot to the right of the
         row, same collapsing rules as the category strip's side slot. --}}
    @if ($nearYou->isNotEmpty())
        <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
            <div class="flex items-center justify-between">
                <h2 class="text-h2 fade-in-section">{{ __('site.home_near_you') }}</h2>
                <a href="{{ route('web.search') }}?sort=nearby" class="text-sm font-medium text-sokoni-black/60 hover:underline">{{ __('site.see_all') }}</a>
            </div>
            <div class="mt-16 flex gap-16 lg:gap-24">
                <div class="no-scrollbar flex flex-1 gap-16 overflow-x-auto pb-8 sm:grid sm:grid-cols-3 sm:overflow-visible md:grid-cols-4 lg:grid-cols-6">
                    {{-- B2 (tester feedback): this is the first product grid on
                         the page — its first row sits at or near the fold, so
                         lazy-loading it (the default for every other card)
                         just defers the very photos a visitor sees first,
                         reading as a pop-in/flicker of its own on a slow
                         connection. Eager + high fetch priority for the first
                         4 (a typical above-the-fold count across breakpoints);
                         everything after stays lazy as before. --}}
                    @foreach ($nearYou as $product)
                        <div class="w-[160px] shrink-0 sm:w-auto">
                            <x-product-card :product="$product" :lazy="$loop->index >= 4" />
                        </div>
                    @endforeach
                </div>
                <x-banner-slot position="near_you_side" variant="side" class="hidden lg:block" />
            </div>
        </section>
    @endif

    {{-- Live offers --}}
    @if ($offers->isNotEmpty())
        <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
            <div class="flex items-center justify-between">
                <h2 class="text-h2 fade-in-section">{{ __('site.home_offers') }}</h2>
                <a href="{{ route('web.search') }}?sponsored=1" class="text-sm font-medium text-sokoni-black/60 hover:underline">{{ __('site.home_offers_see_all') }}</a>
            </div>
            <div class="no-scrollbar mt-16 flex gap-16 overflow-x-auto pb-8 lg:gap-24">
                @foreach ($offers as $offer)
                    <div class="w-[224px] shrink-0 sm:w-[256px]">
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
            <h2 class="text-h2 fade-in-section">{{ __('site.home_featured_shops') }}</h2>
            <div class="no-scrollbar mt-16 flex gap-16 overflow-x-auto pb-8 sm:grid sm:grid-cols-4 sm:overflow-visible md:grid-cols-8 lg:gap-24">
                @foreach ($featuredShops as $shop)
                    <div class="w-[120px] shrink-0 sm:w-auto">
                        <x-shop-card :shop="$shop" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <x-banner-slot position="home_mid" />

    {{-- Latest listings --}}
    <section class="mx-auto max-w-7xl px-16 py-16 lg:px-24">
        <div class="flex items-center justify-between">
            <h2 class="text-h2 fade-in-section">{{ __('site.home_latest') }}</h2>
            <a href="{{ route('web.search') }}?sort=newest" class="text-sm font-medium text-sokoni-black/60 hover:underline">{{ __('site.see_all') }}</a>
        </div>
        <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 lg:gap-24">
            @foreach ($latest as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>

    {{-- Trust band — three equal columns on desktop, stacked on mobile; inline
         SVG icons in Sokoni yellow rather than an icon font (tester feedback
         item 9). --}}
    <section class="border-y border-sokoni-outline bg-sokoni-surface-alt">
        <div class="mx-auto grid max-w-7xl gap-32 px-16 py-40 sm:grid-cols-3 lg:px-24">
            <div class="flex flex-col items-center text-center sm:items-start sm:text-left">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-32 w-32 text-sokoni-yellow">
                    <path d="M12 3l7 3v5c0 4.5-3 8.25-7 9.75C8 19.25 5 15.5 5 11V6l7-3z" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <h3 class="mt-12 text-sm font-semibold">{{ __('site.trust_verified_sellers') }}</h3>
                <p class="mt-4 text-xs text-sokoni-black/60">{{ __('site.trust_verified_sellers_desc') }}</p>
            </div>
            <div class="flex flex-col items-center text-center sm:items-start sm:text-left">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-32 w-32 text-sokoni-yellow">
                    <rect x="5" y="10" width="14" height="10" rx="2" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="1.5" />
                    <path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    <circle cx="12" cy="14.5" r="1.5" fill="currentColor" />
                </svg>
                <h3 class="mt-12 text-sm font-semibold">{{ __('site.trust_secure_ordering') }}</h3>
                <p class="mt-4 text-xs text-sokoni-black/60">{{ __('site.trust_secure_ordering_desc') }}</p>
            </div>
            <div class="flex flex-col items-center text-center sm:items-start sm:text-left">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-32 w-32 text-sokoni-yellow">
                    <path d="M12 21s-7-6.5-7-11.5A7 7 0 0112 2a7 7 0 017 7.5C19 14.5 12 21 12 21z" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                    <circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5" />
                </svg>
                <h3 class="mt-12 text-sm font-semibold">{{ __('site.trust_anywhere_tanzania') }}</h3>
                <p class="mt-4 text-xs text-sokoni-black/60">{{ __('site.trust_anywhere_tanzania_desc') }}</p>
            </div>
        </div>
    </section>

    {{-- App download banner — this page's second job. Hidden entirely until a real Google Play link is configured. --}}
    @if ($googlePlay = config('sokoni.app_links.google_play'))
        <section id="app-download" class="mx-16 my-32 rounded-card bg-sokoni-black px-24 py-32 text-center text-white lg:mx-auto lg:max-w-7xl">
            <h2 class="text-2xl font-bold">{{ __('site.home_app_banner_title') }}</h2>
            <p class="mx-auto mt-8 max-w-md text-white/70">{{ __('site.home_app_banner_body') }}</p>
            <a href="{{ $googlePlay }}" class="btn-primary mt-20 inline-flex">{{ __('site.home_app_banner_cta') }}</a>
        </section>
    @endif
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

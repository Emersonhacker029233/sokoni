@php
    $currentLocale = app()->getLocale();
    $webUser = auth('web')->user();
    // The hero on the home page IS the site's dominant search — the header's
    // own copy must never also render there (Part 2 design pass: they were
    // both showing full-size on desktop simultaneously). Compact header
    // search is for every OTHER page, where there's no hero to carry it.
    $isHomePage = request()->routeIs('web.home');
    $locales = ['en' => 'English', 'sw' => 'Kiswahili'];
@endphp
<header
    class="sticky top-0 z-40 bg-white transition-shadow duration-200"
    x-data="{ mobileOpen: false, scrolled: false }"
    x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 100 })"
    :class="{ 'shadow-md': scrolled }"
>
    {{-- Tier 1: logo, search, region/language/account (Part 3 header spec — single row, 72px). --}}
    <div class="border-b border-sokoni-outline">
        <div class="mx-auto flex h-72 max-w-7xl items-center gap-16 px-16 lg:px-24">
            {{-- 40px tall — CLAUDE.md/tester feedback item 5: previously 32px read as
                 an afterthought next to the nav and search bar around it. Wordmark used
                 to be hidden below the sm breakpoint entirely (tester feedback A7: "the
                 site name doesn't show at the top" on mobile) — now always visible,
                 just a touch smaller on the smallest screens to leave room alongside it. --}}
            <a href="{{ route('web.home') }}" class="flex shrink-0 items-center gap-8">
                <img src="{{ asset('images/brand/sokoni_logo_icon.png') }}" alt="Sokoni" class="h-40 w-40 rounded-chip">
                <span class="text-lg font-bold sm:text-xl">Sokoni</span>
            </a>

            {{-- Primary nav — the 5 destinations, desktop only (mobile gets the fixed bottom bar instead). --}}
            <nav aria-label="Primary" class="hidden shrink-0 items-center gap-4 lg:flex">
                @foreach ([
                    ['route' => 'web.home', 'pattern' => 'web.home', 'label' => __('site.nav_home')],
                    ['route' => 'web.stores', 'pattern' => 'web.stores', 'label' => __('site.nav_stores')],
                    ['route' => 'web.explore', 'pattern' => 'web.explore', 'label' => __('site.nav_explore')],
                    ['route' => 'web.chats', 'pattern' => 'web.chats*|web.account.messages*', 'label' => __('site.nav_chats')],
                    ['route' => 'web.profile', 'pattern' => 'web.profile*|web.account.dashboard|web.account.orders*|web.account.saved|web.account.settings|web.account.shop*', 'label' => __('site.nav_profile')],
                ] as $item)
                    <a href="{{ route($item['route']) }}" class="rounded-chip px-12 py-8 text-sm font-medium {{ request()->routeIs(...explode('|', $item['pattern'])) ? 'text-sokoni-black font-semibold' : 'text-sokoni-black/60 hover:text-sokoni-black' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Compact search for inner pages — the home page carries its own dominant hero search instead. --}}
            <form
                @if ($isHomePage) style="display:none" @endif
                action="{{ route('web.search') }}" method="get" class="hidden max-w-[480px] flex-1 items-stretch overflow-hidden rounded-chip border border-sokoni-outline md:flex">
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="{{ __('site.search_placeholder') }}"
                    class="min-w-0 flex-1 border-none px-16 py-14 text-sm focus:outline-none focus:ring-0"
                    aria-label="{{ __('site.search_placeholder') }}"
                >
                <label class="sr-only" for="header-region">{{ __('site.filter_region') }}</label>
                <select id="header-region" name="region" class="hidden border-x border-sokoni-outline bg-sokoni-surface-alt px-12 text-sm text-sokoni-black/70 sm:block">
                    <option value="">{{ __('site.search_region_all') }}</option>
                    @foreach (\App\Support\TanzaniaRegions::options() as $slug => $name)
                        <option value="{{ $slug }}" @selected(request('region') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="flex items-center bg-sokoni-yellow px-16 text-sokoni-black" aria-label="{{ __('site.search_submit') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-20 w-20"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
                </button>
            </form>

            <div class="ml-auto flex items-center gap-8">
                {{-- Language dropdown — globe icon, current language, a panel on click. Language only; see DECISIONS.md for why there's deliberately no currency switcher next to it. --}}
                <div class="relative hidden sm:block" x-data="{ langOpen: false }">
                    <button
                        type="button"
                        @click="langOpen = !langOpen"
                        @click.outside="langOpen = false"
                        class="flex items-center gap-4 rounded-chip px-8 py-8 text-sm font-medium text-sokoni-black/70 hover:bg-sokoni-surface-alt"
                        :aria-expanded="langOpen"
                        aria-haspopup="true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-20 w-20">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
                        </svg>
                        <span>{{ strtoupper($currentLocale) }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-14 w-14"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                    </button>
                    <div
                        x-show="langOpen"
                        x-cloak
                        x-transition
                        class="absolute right-0 top-full z-50 mt-4 w-160 overflow-hidden rounded-chip border border-sokoni-outline bg-white py-4 shadow-md"
                    >
                        @foreach ($locales as $code => $label)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                               class="flex items-center justify-between px-12 py-8 text-sm {{ $currentLocale === $code ? 'font-semibold text-sokoni-black' : 'text-sokoni-black/70 hover:bg-sokoni-surface-alt' }}">
                                {{ $label }}
                                @if ($currentLocale === $code)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16 text-sokoni-yellow"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Sign-in/account is no longer a separate button — "Profile", one of the 5 primary
                     nav destinations above, is the single account entry point (sign-in prompt when
                     signed out, straight to the dashboard when signed in). --}}
                <a href="{{ route('web.sell') }}" class="btn-secondary hidden sm:inline-flex">{{ __('site.nav_sell') }}</a>

                {{-- min-h/w-44 is the real tap target (was a 24px icon + 8px padding = 40px,
                     just under the 44px minimum — tester feedback A7); the icon itself
                     stays a legible 24px, centred inside the larger touch area. --}}
                <button type="button" class="flex min-h-44 min-w-44 items-center justify-center lg:hidden" @click="mobileOpen = !mobileOpen" aria-label="Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile/tablet menu — visible up to the same lg breakpoint the hamburger button itself uses. --}}
    <div x-show="mobileOpen" x-cloak class="border-t border-sokoni-outline bg-white px-16 py-16 lg:hidden">
        <form action="{{ route('web.search') }}" method="get" class="mb-16 flex overflow-hidden rounded-chip border border-sokoni-outline">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('site.search_placeholder') }}" class="min-w-0 flex-1 border-none px-16 py-14 text-sm focus:outline-none focus:ring-0">
            <button type="submit" class="bg-sokoni-yellow px-16 text-sokoni-black">{{ __('site.search_submit') }}</button>
        </form>
        <nav class="flex flex-col gap-4">
            {{-- Sign-in/account: the "Profile" item in the fixed bottom nav covers this on mobile. --}}
            <a href="{{ route('web.sell') }}" class="rounded-chip px-12 py-10 font-medium hover:bg-sokoni-surface-alt">{{ __('site.nav_sell') }}</a>
            @if ($webUser)
                <form action="{{ route('web.logout') }}" method="post">
                    @csrf
                    <button type="submit" class="w-full rounded-chip px-12 py-10 text-left font-medium hover:bg-sokoni-surface-alt">{{ __('site.nav_sign_out') }}</button>
                </form>
            @endif
            <div class="mt-8 flex gap-8 border-t border-sokoni-outline pt-12">
                @foreach ($locales as $code => $label)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="chip {{ $currentLocale === $code ? 'chip-active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
    </div>

    {{-- Tier 2: dark secondary bar carrying the category strip — Amazon's two-tier structure, so category
         nav has its own home instead of competing with the hero for attention. Horizontal scroll, no
         visible scrollbar, fade masks at both edges, active category in yellow. --}}
    {{-- The scroll-edge fade used to be a mask-image on this whole element —
         which faded the element's own solid background to transparent at
         each edge too, letting the white page show through as artefacts at
         both corners (tester feedback item 4). Fixed at the container: the
         nav stays a plain solid rectangle, and the two ::-equivalent overlay
         divs below fade FROM the same black TO transparent, layered on top
         of the content — same visual cue, no hole in the background. --}}
    <nav aria-label="Categories" class="relative hidden bg-sokoni-black lg:block">
        <div class="no-scrollbar mx-auto flex max-w-7xl gap-4 overflow-x-auto px-16 py-8 lg:px-24">
            @foreach (($navCategories ?? []) as $navCategory)
                @php($isActiveCategory = request()->routeIs('web.category') && request()->route('category') === app(\App\Services\Catalog\CategoryCatalogService::class)->slug($navCategory))
                {{-- Name only, deliberately no product count — a low count here read as
                     "this marketplace is empty" rather than as useful information (tester
                     feedback item 3). Counts stay on the category landing page itself,
                     where a visitor has already committed to that category and the number
                     is genuine context, not a first impression. --}}
                <a href="{{ route('web.category', app(\App\Services\Catalog\CategoryCatalogService::class)->slug($navCategory)) }}"
                   class="shrink-0 rounded-chip px-12 py-6 text-sm {{ $isActiveCategory ? 'bg-sokoni-yellow font-semibold text-sokoni-black' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                    {{ $navCategory->name($currentLocale) }}
                </a>
            @endforeach
        </div>
        <div class="pointer-events-none absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-sokoni-black to-transparent" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-sokoni-black to-transparent" aria-hidden="true"></div>
    </nav>
</header>

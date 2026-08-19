@php
    $currentLocale = app()->getLocale();
    $webUser = auth('web')->user();
@endphp
<header class="sticky top-0 z-40 border-b border-sokoni-outline bg-white" x-data="{ mobileOpen: false }">
    <div class="mx-auto flex max-w-7xl items-center gap-16 px-16 py-12 lg:px-24">
        <a href="{{ route('web.home') }}" class="flex shrink-0 items-center gap-8">
            <img src="{{ asset('images/brand/sokoni_logo_icon.png') }}" alt="Sokoni" class="h-32 w-32 rounded-chip">
            <span class="hidden text-lg font-bold sm:inline">Sokoni</span>
        </a>

        {{-- Dominant search — the Jiji reference: a single input plus a region selector, always visible. --}}
        <form action="{{ route('web.search') }}" method="get" class="hidden flex-1 items-stretch overflow-hidden rounded-chip border border-sokoni-outline md:flex">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="{{ __('site.search_placeholder') }}"
                class="min-w-0 flex-1 border-none px-16 py-10 text-sm focus:outline-none focus:ring-0"
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
            {{-- Language switcher --}}
            <div class="hidden items-center gap-4 text-sm font-medium sm:flex">
                @foreach (['en' => 'EN', 'sw' => 'SW'] as $code => $label)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                       class="rounded-chip px-8 py-4 {{ $currentLocale === $code ? 'bg-sokoni-surface-alt font-semibold' : 'text-sokoni-black/50 hover:text-sokoni-black' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <a href="{{ route('web.sell') }}" class="btn-secondary hidden sm:inline-flex">{{ __('site.nav_sell') }}</a>

            @if ($webUser)
                <a href="{{ route('web.account.dashboard') }}" class="btn-ghost hidden sm:inline-flex">{{ __('site.nav_my_account') }}</a>
            @else
                <a href="{{ route('web.login') }}" class="btn-primary">{{ __('site.nav_sign_in') }}</a>
            @endif

            <button type="button" class="p-8 md:hidden" @click="mobileOpen = !mobileOpen" aria-label="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </div>

    {{-- Mobile search + menu --}}
    <div x-show="mobileOpen" x-cloak class="border-t border-sokoni-outline bg-white px-16 py-16 md:hidden">
        <form action="{{ route('web.search') }}" method="get" class="mb-16 flex overflow-hidden rounded-chip border border-sokoni-outline">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('site.search_placeholder') }}" class="min-w-0 flex-1 border-none px-16 py-10 text-sm focus:outline-none focus:ring-0">
            <button type="submit" class="bg-sokoni-yellow px-16 text-sokoni-black">{{ __('site.search_submit') }}</button>
        </form>
        <nav class="flex flex-col gap-4">
            <a href="{{ route('web.sell') }}" class="rounded-chip px-12 py-10 font-medium hover:bg-sokoni-surface-alt">{{ __('site.nav_sell') }}</a>
            @if ($webUser)
                <a href="{{ route('web.account.dashboard') }}" class="rounded-chip px-12 py-10 font-medium hover:bg-sokoni-surface-alt">{{ __('site.nav_my_account') }}</a>
            @else
                <a href="{{ route('web.login') }}" class="rounded-chip px-12 py-10 font-medium hover:bg-sokoni-surface-alt">{{ __('site.nav_sign_in') }}</a>
            @endif
            <div class="mt-8 flex gap-8 border-t border-sokoni-outline pt-12">
                @foreach (['en' => 'English', 'sw' => 'Kiswahili'] as $code => $label)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="chip {{ $currentLocale === $code ? 'chip-active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
    </div>

    {{-- Category strip --}}
    <nav aria-label="Categories" class="hidden overflow-x-auto border-t border-sokoni-outline bg-sokoni-surface-alt/50 lg:block">
        <div class="mx-auto flex max-w-7xl gap-4 px-16 py-8 lg:px-24">
            @foreach (($navCategories ?? []) as $navCategory)
                <a href="{{ route('web.category', app(\App\Services\Catalog\CategoryCatalogService::class)->slug($navCategory)) }}"
                   class="shrink-0 rounded-chip px-12 py-6 text-sm text-sokoni-black/70 hover:bg-white hover:text-sokoni-black">
                    {{ $navCategory->name($currentLocale) }}
                    <span class="text-sokoni-black/40">({{ $navCategory->products_count }})</span>
                </a>
            @endforeach
        </div>
    </nav>
</header>

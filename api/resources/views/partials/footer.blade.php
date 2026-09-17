@php($currentLocale = app()->getLocale())

{{-- Dark background to close the page (Part 3 footer spec) — the one place
     on the site that deliberately breaks from the black-on-white system. --}}
<footer class="bg-sokoni-black text-white/80">
    <div class="mx-auto grid max-w-7xl grid-cols-2 gap-24 px-16 py-40 sm:grid-cols-4 lg:px-24 lg:py-64">
        <div class="col-span-2 sm:col-span-1">
            {{-- 40px, same treatment as the header logo (tester feedback item 8). --}}
            <a href="{{ route('web.home') }}" class="flex items-center gap-8">
                <img src="{{ asset('images/brand/sokoni_logo_icon.png') }}" alt="Sokoni" class="h-40 w-40 rounded-chip">
                <span class="text-xl font-bold text-white">Sokoni</span>
            </a>
            <p class="mt-12 text-sm text-white/60">{{ __('site.home_hero_subtitle') }}</p>

            {{-- Social links: hidden entirely (not linked to a dead page) when unset in config/sokoni.php --}}
            @if (config('sokoni.social.facebook') || config('sokoni.social.instagram') || config('sokoni.social.x'))
                <div class="mt-16 flex items-center gap-12">
                    @if ($facebook = config('sokoni.social.facebook'))
                        <a href="{{ $facebook }}" aria-label="Facebook" class="text-white/50 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-20 w-20"><path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z" /></svg>
                        </a>
                    @endif
                    @if ($instagram = config('sokoni.social.instagram'))
                        <a href="{{ $instagram }}" aria-label="Instagram" class="text-white/50 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-20 w-20"><path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.22.6 1.77 1.15.55.55.89 1.11 1.15 1.77.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 01-1.15 1.77 4.9 4.9 0 01-1.77 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 01-1.77-1.15 4.9 4.9 0 01-1.15-1.77c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.22 1.15-1.77A4.9 4.9 0 015.45 2.53c.64-.25 1.37-.42 2.43-.47C8.94 2.01 9.28 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm0 8.2a3.2 3.2 0 110-6.4 3.2 3.2 0 010 6.4zm5.2-8.4a1.17 1.17 0 11-2.34 0 1.17 1.17 0 012.34 0z" /></svg>
                        </a>
                    @endif
                    @if ($x = config('sokoni.social.x'))
                        <a href="{{ $x }}" aria-label="X (Twitter)" class="text-white/50 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-20 w-20"><path d="M18.9 2.5h3.3l-7.2 8.2 8.5 11.3h-6.6l-5.2-6.8-5.9 6.8H2.5l7.7-8.8L2 2.5h6.8l4.7 6.3 5.4-6.3zm-1.2 17.4h1.8L7.4 4.4H5.5l12.2 15.5z" /></svg>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <h3 class="text-caption text-white/50">{{ __('site.footer_about_heading') }}</h3>
            <ul class="mt-12 space-y-8 text-sm">
                <li><a href="{{ route('web.about') }}" class="hover:underline">{{ __('site.footer_about_link') }}</a></li>
                <li><a href="{{ route('web.how-it-works') }}" class="hover:underline">{{ __('site.footer_how_it_works_link') }}</a></li>
                <li><a href="{{ route('web.contact') }}" class="hover:underline">{{ __('site.footer_contact_link') }}</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-caption text-white/50">{{ __('site.footer_categories_heading') }}</h3>
            <ul class="mt-12 space-y-8 text-sm">
                @foreach (($navCategories ?? collect())->take(5) as $navCategory)
                    <li><a href="{{ route('web.category', app(\App\Services\Catalog\CategoryCatalogService::class)->slug($navCategory)) }}" class="hover:underline">{{ $navCategory->name($currentLocale) }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="text-caption text-white/50">{{ __('site.footer_sellers_heading') }}</h3>
            <ul class="mt-12 space-y-8 text-sm">
                <li><a href="{{ route('web.sell') }}" class="hover:underline">{{ __('site.footer_start_selling_link') }}</a></li>
            </ul>

            <h3 class="text-caption mt-24 text-white/50">{{ __('site.footer_support_heading') }}</h3>
            <ul class="mt-12 space-y-8 text-sm">
                <li><a href="{{ route('web.safety') }}" class="hover:underline">{{ __('site.footer_safety_link') }}</a></li>
                <li><a href="{{ route('web.terms') }}" class="hover:underline">{{ __('site.footer_terms_link') }}</a></li>
                <li><a href="{{ route('web.privacy') }}" class="hover:underline">{{ __('site.footer_privacy_link') }}</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10 px-16 py-16 lg:px-24">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-16 sm:flex-row sm:justify-between">
            <p class="text-caption text-white/40">&copy; {{ now()->year }} {{ __('site.footer_copyright') }}</p>

            <div class="flex items-center gap-16">
                <div class="flex items-center gap-4 text-sm font-medium">
                    @foreach (['en' => 'EN', 'sw' => 'SW'] as $code => $label)
                        <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                           class="rounded-chip px-8 py-4 {{ $currentLocale === $code ? 'bg-white/10 font-semibold text-white' : 'text-white/50 hover:text-white' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                {{-- App Store link removed entirely — no iOS build exists yet. Add it back once one ships. --}}
                @if ($googlePlay = config('sokoni.app_links.google_play'))
                    <a href="{{ $googlePlay }}" aria-label="{{ __('site.a11y_get_it_on_google_play') }}" class="inline-flex h-40 items-center rounded-chip border border-white/20 px-12 text-xs font-medium text-white/70 hover:border-white/40 hover:text-white">Google Play</a>
                @endif
            </div>
        </div>
    </div>
</footer>

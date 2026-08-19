<footer class="border-t border-sokoni-outline bg-sokoni-surface-alt/40">
    <div class="mx-auto grid max-w-7xl grid-cols-2 gap-24 px-16 py-32 sm:grid-cols-4 lg:px-24">
        <div class="col-span-2 sm:col-span-1">
            <a href="{{ route('web.home') }}" class="flex items-center gap-8">
                <img src="{{ asset('images/brand/sokoni_logo_icon.png') }}" alt="Sokoni" class="h-32 w-32 rounded-chip">
                <span class="text-lg font-bold">Sokoni</span>
            </a>
            <p class="mt-12 text-sm text-sokoni-black/60">{{ __('site.home_hero_subtitle') }}</p>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-sokoni-black/50">Sokoni</h3>
            <ul class="mt-12 space-y-8 text-sm">
                <li><a href="{{ route('web.about') }}" class="hover:underline">About</a></li>
                <li><a href="{{ route('web.how-it-works') }}" class="hover:underline">How it works</a></li>
                <li><a href="{{ route('web.sell') }}" class="hover:underline">Start selling</a></li>
                <li><a href="{{ route('web.contact') }}" class="hover:underline">Contact</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-sokoni-black/50">Trust &amp; safety</h3>
            <ul class="mt-12 space-y-8 text-sm">
                <li><a href="{{ route('web.safety') }}" class="hover:underline">Safety tips</a></li>
                <li><a href="{{ route('web.terms') }}" class="hover:underline">Terms of service</a></li>
                <li><a href="{{ route('web.privacy') }}" class="hover:underline">Privacy policy</a></li>
            </ul>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-sokoni-black/50">{{ __('site.home_app_banner_title') }}</h3>
            <p class="mt-12 text-sm text-sokoni-black/60">{{ __('site.home_app_banner_body') }}</p>
            <a href="#app-download" class="btn-secondary mt-12 inline-flex text-xs">{{ __('site.home_app_banner_cta') }}</a>
        </div>
    </div>

    <div class="border-t border-sokoni-outline px-16 py-16 text-center text-xs text-sokoni-black/40 lg:px-24">
        &copy; {{ now()->year }} Sokoni. Made in Dar es Salaam, Tanzania.
    </div>
</footer>

@if ($shops->isNotEmpty())
    <div class="mb-24">
        <h2 class="mb-12 text-sm font-semibold text-sokoni-black/60">{{ __('site.search_shops_heading') }}</h2>
        <div class="grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:gap-24">
            @foreach ($shops as $shop)
                <x-shop-card :shop="$shop" />
            @endforeach
        </div>
    </div>
@endif

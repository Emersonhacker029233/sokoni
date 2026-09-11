@props(['shop'])

<a href="{{ route('web.shop', $shop->handle) }}" class="product-card flex h-full flex-col items-center gap-8 p-16 text-center">
    <div class="relative">
        @if ($shop->logo)
            {{-- Part A (client feedback): never blank on a failed load —
                 hides the broken <img> and reveals the same initial-letter
                 fallback shown below for a shop with no logo at all. --}}
            <img
                src="{{ $shop->logo }}"
                alt="{{ $shop->shop_name }}"
                loading="lazy"
                class="h-64 w-64 rounded-full object-cover ring-1 ring-sokoni-outline"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
            >
            <div class="hidden h-64 w-64 items-center justify-center rounded-full bg-sokoni-surface-alt text-lg font-bold text-sokoni-black/40 ring-1 ring-sokoni-outline" style="display:none">
                {{ strtoupper(substr($shop->shop_name, 0, 1)) }}
            </div>
        @else
            <div class="flex h-64 w-64 items-center justify-center rounded-full bg-sokoni-surface-alt text-lg font-bold text-sokoni-black/40 ring-1 ring-sokoni-outline">
                {{ strtoupper(substr($shop->shop_name, 0, 1)) }}
            </div>
        @endif
        @if ($shop->isVerified())
            <span class="badge-verified absolute -bottom-2 -right-2 h-18 w-18">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-full w-full"><path d="M438-452-58-57q-11-11-27.5-11T324-508q-11 11-11 28t11 28l86 86q12 12 28 12t28-12l170-170q12-12 11.5-28T636-592q-12-12-28.5-12.5T579-593L438-452ZM326-90l-58-98-110-24q-15-3-24-15.5t-7-27.5l11-113-75-86q-10-11-10-26t10-26l75-86-11-113q-2-15 7-27.5t24-15.5l110-24 58-98q8-13 22-17.5t28 1.5l104 44 104-44q14-6 28-1.5t22 17.5l58 98 110 24q15 3 24 15.5t7 27.5l-11 113 75 86q10 11 10 26t-10 26l-75 86 11 113q2 15-7 27.5T802-212l-110 24-58 98q-8 13-22 17.5T584-74l-104-44-104 44q-14 6-28 1.5T326-90Z" /></svg>
            </span>
        @endif
    </div>
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold">{{ $shop->shop_name }}</p>
        <p class="mt-2 flex items-center justify-center gap-4 text-xs text-sokoni-black/50">
            <x-star-rating :rating="(float) $shop->rating_avg" size="12" />
            <span>({{ $shop->rating_count }})</span>
        </p>
        @if ($shop->district || $shop->region)
            <p class="mt-2 truncate text-xs text-sokoni-black/40">{{ $shop->district ?: $shop->region }}</p>
        @endif
        @if (isset($shop->products_count))
            <p class="mt-2 text-xs text-sokoni-black/40">{{ __('site.stores_listing_count', ['count' => $shop->products_count]) }}</p>
        @endif
    </div>
</a>

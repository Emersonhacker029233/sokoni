@props(['shop'])

<a href="{{ route('web.shop', $shop->handle) }}" class="card flex flex-col items-center gap-8 p-16 text-center transition hover:shadow-md">
    <div class="relative">
        @if ($shop->logo)
            <img src="{{ $shop->logo }}" alt="{{ $shop->shop_name }}" loading="lazy" class="h-64 w-64 rounded-full object-cover ring-1 ring-sokoni-outline">
        @else
            <div class="flex h-64 w-64 items-center justify-center rounded-full bg-sokoni-surface-alt text-lg font-bold text-sokoni-black/40 ring-1 ring-sokoni-outline">
                {{ strtoupper(substr($shop->shop_name, 0, 1)) }}
            </div>
        @endif
        @if ($shop->isVerified())
            <span class="badge-verified absolute -bottom-2 -right-2 h-18 w-18 ring-2 ring-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-11 w-11"><path fill-rule="evenodd" d="M10 1l2.39 1.36L15 2l.61 2.61L18 5.99l-1.36 2.39L18 10.77 15.61 12l-.61 2.61L12 14l-2 1.99L8 14l-2.61.61L5 12 2.39 10.77 4 8.38 2.39 5.99 5 5l.39-2.39L8 2z" clip-rule="evenodd" /></svg>
            </span>
        @endif
    </div>
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold">{{ $shop->shop_name }}</p>
        <p class="mt-2 flex items-center justify-center gap-4 text-xs text-sokoni-black/50">
            <x-star-rating :rating="(float) $shop->rating_avg" size="12" />
            <span>({{ $shop->rating_count }})</span>
        </p>
    </div>
</a>

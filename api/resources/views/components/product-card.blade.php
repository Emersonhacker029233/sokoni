@props(['product', 'lazy' => true])

@php
    $cover = $product->media->first();
    $offer = $product->relationLoaded('activeOffer') ? $product->activeOffer : null;
    $isSponsored = $product->is_sponsored && $product->sponsored_until?->isFuture();
    $productUrl = route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)]);
@endphp

<a href="{{ $productUrl }}" class="product-card group flex h-full flex-col">
    <div class="skeleton relative aspect-square overflow-hidden rounded-t-[12px] bg-sokoni-surface-alt">
        @if ($cover)
            <img
                src="{{ $cover->card_path ?? $cover->path }}"
                srcset="{{ $cover->thumb_path }} 300w, {{ $cover->card_path }} 800w"
                sizes="(min-width: 1024px) 260px, 45vw"
                alt="{{ $product->title }}"
                loading="{{ $lazy ? 'lazy' : 'eager' }}"
                decoding="async"
                class="h-full w-full object-cover"
            >
            @if ($cover->isVideo())
                <span class="absolute bottom-8 right-8 rounded-full bg-black/60 p-6 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-12 w-12"><path d="M6.3 2.8a1 1 0 00-1.5.87v12.66a1 1 0 001.5.87l11-6.33a1 1 0 000-1.74l-11-6.33z" /></svg>
                </span>
            @endif
        @else
            <div class="flex h-full w-full items-center justify-center text-sokoni-black/20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-40 w-40"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 4.5h18M3 4.5v15a1.5 1.5 0 001.5 1.5h15a1.5 1.5 0 001.5-1.5v-15" /></svg>
            </div>
        @endif

        {{-- Offer takes precedence over Sponsored when both apply — a live discount is the stronger buyer signal. --}}
        @if ($offer)
            <span class="absolute left-8 top-8 inline-flex items-center rounded-chip bg-sokoni-yellow px-8 py-4 text-xs font-semibold text-sokoni-black">{{ __('site.offer_badge', ['percent' => $offer->discount_type === 'percent' ? (int) $offer->discount_value : round((1 - $offer->discountedPrice() / max($offer->price_snapshot, 1)) * 100)]) }}</span>
        @elseif ($isSponsored)
            <span class="badge-sponsored absolute left-8 top-8">{{ __('site.sponsored_label') }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-4 p-12">
        <div class="flex items-baseline gap-8">
            <span class="text-[18px] font-semibold text-sokoni-black">{{ \App\Support\Money::format($offer ? $offer->discountedPrice() : $product->price) }}</span>
            @if ($offer)
                <span class="text-xs text-sokoni-black/40 line-through">{{ \App\Support\Money::format((int) $offer->price_snapshot) }}</span>
            @endif
        </div>

        <p class="line-clamp-2 text-sm font-normal text-sokoni-black/80">{{ $product->title }}</p>

        <div class="text-caption mt-auto flex items-center gap-4 pt-4">
            @if ($product->seller?->isVerified())
                <span class="badge-verified h-14 w-14 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-10 w-10"><path fill-rule="evenodd" d="M10 1l2.39 1.36L15 2l.61 2.61L18 5.99l-1.36 2.39L18 10.77 15.61 12l-.61 2.61L12 14l-2 1.99L8 14l-2.61.61L5 12 2.39 10.77 4 8.38 2.39 5.99 5 5l.39-2.39L8 2z" clip-rule="evenodd" /></svg>
                </span>
            @endif
            <span class="truncate">{{ $product->seller?->shop_name }}</span>
            @if (isset($product->distance_km))
                <span class="ml-auto shrink-0">{{ number_format($product->distance_km, 1) }} km</span>
            @endif
        </div>
    </div>
</a>

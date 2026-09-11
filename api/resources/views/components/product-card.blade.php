@props(['product', 'lazy' => true])

@php
    $cover = $product->media->first();
    $offer = $product->relationLoaded('activeOffer') ? $product->activeOffer : null;
    $isSponsored = $product->is_sponsored && $product->sponsored_until?->isFuture();
    $productUrl = route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)]);
@endphp

<a href="{{ $productUrl }}" class="product-card group flex h-full flex-col">
    {{-- A2 (tester feedback, re-diagnosed from scratch): this container
         carried the shared `.skeleton` class — `animate-pulse`, an
         infinite CSS animation — with nothing anywhere that ever removed
         it once the real photo loaded. The <img> below sits fully opaque
         on top of it (h-full w-full object-cover), so the constant pulsing
         compositing underneath was invisible in the steady state but
         showed through at the rounded corners and on any partial-paint
         moment as a persistent flicker — on every product card, on every
         page that uses this component, which is why it read as "still
         broken" after the earlier, unrelated PDP-gallery x-cloak fix. A
         static placeholder background needs no animation at all; the
         plain surface colour alone (kept below) already does that job. --}}
    {{-- B2 (tester feedback, re-diagnosed live): considered — and
         deliberately rejected — adding a JS-driven opacity crossfade here
         (Alpine `@load` + `x-init` checking `$refs.img.complete` for
         already-cached images). It has no safe failure mode: default the
         image to `opacity-0` and it stays invisible forever if Alpine
         fails to load for any reason (a blocked script, a slow connection
         racing the fetch); default it to `opacity-100` and there's a
         window between first paint and Alpine hydrating where the image
         can flash visible then snap invisible then fade back in — a worse
         flicker than the one being fixed. A plain `<img>` popping in once
         decoded, with no animation at all, is the safer choice; the
         reserved aspect-ratio box + solid background below it already
         does the actual job (no layout shift, no visible "hole"). --}}
    <div class="relative aspect-square overflow-hidden rounded-t-[12px] bg-sokoni-surface-alt">
        @if ($cover)
            <img
                src="{{ $cover->card_path ?? $cover->path }}"
                srcset="{{ $cover->thumb_path }} 300w, {{ $cover->card_path }} 800w"
                sizes="(min-width: 1024px) 260px, 45vw"
                alt="{{ $product->title }}"
                loading="{{ $lazy ? 'lazy' : 'eager' }}"
                @unless ($lazy) fetchpriority="high" @endunless
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-full w-full"><path d="M438-452-58-57q-11-11-27.5-11T324-508q-11 11-11 28t11 28l86 86q12 12 28 12t28-12l170-170q12-12 11.5-28T636-592q-12-12-28.5-12.5T579-593L438-452ZM326-90l-58-98-110-24q-15-3-24-15.5t-7-27.5l11-113-75-86q-10-11-10-26t10-26l75-86-11-113q-2-15 7-27.5t24-15.5l110-24 58-98q8-13 22-17.5t28 1.5l104 44 104-44q14-6 28-1.5t22 17.5l58 98 110 24q15 3 24 15.5t7 27.5l-11 113 75 86q10 11 10 26t-10 26l-75 86 11 113q2 15-7 27.5T802-212l-110 24-58 98q-8 13-22 17.5T584-74l-104-44-104 44q-14 6-28 1.5T326-90Z" /></svg>
                </span>
            @endif
            <span class="truncate">{{ $product->seller?->shop_name }}</span>
            @if (isset($product->distance_km))
                <span class="ml-auto shrink-0">{{ number_format($product->distance_km, 1) }} km</span>
            @endif
        </div>
    </div>
</a>

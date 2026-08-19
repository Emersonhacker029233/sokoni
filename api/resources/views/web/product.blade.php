@extends('layouts.app')

@section('content')
<x-breadcrumb :items="$breadcrumbs" />

<div class="mx-auto max-w-6xl px-16 pb-40 lg:px-24">
    <div class="grid gap-32 lg:grid-cols-2">
        {{-- Gallery --}}
        <div x-data="{ active: 0, lightbox: false, items: {{ $product->media->count() }} }" @keydown.escape.window="lightbox = false" @keydown.arrow-right.window="if (lightbox) active = (active + 1) % items" @keydown.arrow-left.window="if (lightbox) active = (active - 1 + items) % items">
            <div class="relative aspect-square overflow-hidden rounded-card bg-sokoni-surface-alt">
                @forelse ($product->media as $index => $media)
                    <div x-show="active === {{ $index }}" class="absolute inset-0">
                        @if ($media->isVideo())
                            <video src="{{ $media->path }}" poster="{{ $media->thumb_path }}" controls playsinline class="h-full w-full object-cover"></video>
                        @else
                            <img
                                src="{{ $media->card_path ?? $media->path }}"
                                srcset="{{ $media->thumb_path }} 300w, {{ $media->card_path }} 800w, {{ $media->path }} 1600w"
                                sizes="(min-width: 1024px) 50vw, 100vw"
                                alt="{{ $product->title }}"
                                @click="lightbox = true"
                                class="h-full w-full cursor-zoom-in object-cover"
                            >
                        @endif
                    </div>
                @empty
                    <div class="flex h-full items-center justify-center text-sokoni-black/20">No photo</div>
                @endforelse

                @if ($product->media->count() > 1)
                    <button type="button" @click="active = (active - 1 + items) % items" aria-label="Previous" class="absolute left-8 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-8 shadow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg></button>
                    <button type="button" @click="active = (active + 1) % items" aria-label="Next" class="absolute right-8 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-8 shadow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg></button>
                @endif
            </div>

            @if ($product->media->count() > 1)
                <div class="mt-8 flex gap-8 overflow-x-auto">
                    @foreach ($product->media as $index => $media)
                        <button type="button" @click="active = {{ $index }}" class="h-56 w-56 shrink-0 overflow-hidden rounded-chip ring-2" :class="active === {{ $index }} ? 'ring-sokoni-yellow' : 'ring-transparent'">
                            <img src="{{ $media->thumb_path ?? $media->path }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Lightbox --}}
            <div x-show="lightbox" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-16" @click="lightbox = false">
                <button type="button" class="absolute right-16 top-16 text-white" @click="lightbox = false" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-32 w-32"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                @foreach ($product->media as $index => $media)
                    <img x-show="active === {{ $index }} && !{{ $media->isVideo() ? 'true' : 'false' }}" src="{{ $media->path }}" alt="{{ $product->title }}" class="max-h-full max-w-full object-contain" @click.stop>
                @endforeach
            </div>
        </div>

        {{-- Details --}}
        <div>
            <h1 class="text-2xl font-bold">{{ $product->title }}</h1>

            <div class="mt-12 flex items-baseline gap-12">
                <span class="text-2xl font-extrabold">{{ \App\Support\Money::format($offer ? $offer->discountedPrice() : $product->price) }}</span>
                @if ($offer)
                    <span class="text-base text-sokoni-black/40 line-through">{{ \App\Support\Money::format((int) $offer->price_snapshot) }}</span>
                @endif
            </div>

            @if ($offer)
                <p x-data="sokoniCountdown('{{ $offer->ends_at->toIso8601String() }}')" x-init="tick()" class="mt-4 text-sm font-medium text-sokoni-danger">
                    {{ __('site.product_offer_ends') }} <span x-text="label"></span>
                </p>
            @endif

            <div class="mt-16 flex flex-wrap items-center gap-8 text-sm">
                <span class="chip">{{ $product->condition === 'new' ? __('site.filter_condition_new') : __('site.filter_condition_used') }}</span>
                <span class="{{ $product->stock > 0 ? 'text-sokoni-success' : 'text-sokoni-danger' }} font-medium">
                    {{ $product->stock > 0 ? "{$product->stock} ".__('site.product_stock') : __('site.product_out_of_stock') }}
                </span>
            </div>

            {{-- Seller card --}}
            <a href="{{ route('web.shop', $seller->handle) }}" class="card mt-24 flex items-center gap-12 p-16">
                @if ($seller->logo)
                    <img src="{{ $seller->logo }}" alt="{{ $seller->shop_name }}" class="h-48 w-48 rounded-full object-cover">
                @else
                    <div class="flex h-48 w-48 items-center justify-center rounded-full bg-sokoni-surface-alt font-bold">{{ strtoupper(substr($seller->shop_name, 0, 1)) }}</div>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-4 truncate font-semibold">
                        {{ $seller->shop_name }}
                        @if ($seller->isVerified())
                            <span class="badge-verified h-14 w-14"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-10 w-10"><path fill-rule="evenodd" d="M10 1l2.39 1.36L15 2l.61 2.61L18 5.99l-1.36 2.39L18 10.77 15.61 12l-.61 2.61L12 14l-2 1.99L8 14l-2.61.61L5 12 2.39 10.77 4 8.38 2.39 5.99 5 5l.39-2.39L8 2z" clip-rule="evenodd" /></svg></span>
                        @endif
                    </p>
                    <p class="flex items-center gap-8 text-xs text-sokoni-black/50">
                        <x-star-rating :rating="(float) $seller->rating_avg" size="12" />
                        <span>({{ $seller->rating_count }})</span>
                        <span>&middot;</span>
                        <span>{{ __('site.product_member_since', ['date' => $seller->created_at->format('Y')]) }}</span>
                    </p>
                    <p class="mt-2 text-xs text-sokoni-black/40">{{ $seller->district }}, {{ $seller->region }} &middot; {{ __('site.product_listings_count', ['count' => $otherListingsCount]) }}</p>
                </div>
            </a>

            {{-- Contact actions --}}
            <div class="mt-16 grid grid-cols-3 gap-8">
                <a href="{{ auth('web')->check() ? route('web.account.messages') : route('web.login') }}" class="btn-secondary flex-col gap-4 py-12 text-xs">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-20 w-20"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.24 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                    {{ __('site.product_message') }}
                </a>
                @if ($seller->show_whatsapp && $seller->whatsapp)
                    <a href="https://wa.me/{{ ltrim($seller->whatsapp, '+') }}?text={{ urlencode($product->title.' — '.route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)])) }}" target="_blank" rel="noopener" class="btn-secondary flex-col gap-4 py-12 text-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-20 w-20"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2z" /></svg>
                        {{ __('site.product_whatsapp') }}
                    </a>
                @endif
                <div x-data="{ revealed: false }">
                    <template x-if="!revealed">
                        <button
                            type="button"
                            class="btn-secondary w-full flex-col gap-4 py-12 text-xs"
                            @click="
                                revealed = true;
                                fetch('{{ route('web.product.reveal-call', $product->id) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
                            "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-20 w-20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                            <span>{{ __('site.product_call_reveal') }}</span>
                        </button>
                    </template>
                    <a x-show="revealed" x-cloak href="tel:{{ $seller->user->phone }}" class="btn-secondary flex w-full flex-col gap-4 py-12 text-xs">
                        {{ $seller->user->phone }}
                    </a>
                </div>
            </div>

            <a href="{{ route('web.login') }}?intended=order" class="btn-primary mt-16 flex w-full py-14 text-base">{{ __('site.product_order') }}</a>

            <div class="mt-24">
                <h2 class="font-semibold">{{ __('site.product_description') }}</h2>
                <p class="mt-8 whitespace-pre-line text-sm text-sokoni-black/70">{{ $product->description ?: '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Reviews --}}
    <section class="mt-40">
        <h2 class="text-lg font-bold">{{ __('site.product_reviews') }} ({{ $seller->rating_count }})</h2>
        @if ($reviews->isEmpty())
            <p class="mt-8 text-sm text-sokoni-black/50">{{ __('site.product_no_reviews') }}</p>
        @else
            <div class="mt-16 space-y-16">
                @foreach ($reviews as $review)
                    <div class="border-b border-sokoni-outline pb-16">
                        <div class="flex items-center justify-between">
                            <p class="font-medium">{{ $review->buyer->name }}</p>
                            <x-star-rating :rating="(float) $review->rating" size="14" />
                        </div>
                        @if ($review->comment)
                            <p class="mt-4 text-sm text-sokoni-black/70">{{ $review->comment }}</p>
                        @endif
                        @if ($review->hasReply())
                            <div class="mt-8 rounded-chip bg-sokoni-surface-alt p-12 text-sm">
                                <p class="font-medium text-sokoni-black/60">{{ __('site.product_seller_reply') }}</p>
                                <p class="mt-2">{{ $review->reply }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Similar products --}}
    @if ($similar->isNotEmpty())
        <section class="mt-40">
            <h2 class="text-lg font-bold">{{ __('site.product_similar') }}</h2>
            <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-4">
                @foreach ($similar as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- More from this shop --}}
    @if ($moreFromShop->isNotEmpty())
        <section class="mt-40">
            <h2 class="text-lg font-bold">{{ __('site.product_more_from_shop') }}</h2>
            <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-4">
                @foreach ($moreFromShop as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
</div>
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
                this.label = h > 24 ? Math.floor(h / 24) + 'd ' + (h % 24) + 'h' : h + 'h ' + m + 'm';
                setTimeout(() => this.tick(), 60000);
            },
        };
    }
</script>

{{-- schema.org Product markup — CLAUDE.md: "This is what produces rich results in Google." --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->title,
    'image' => $product->media->pluck('path')->values(),
    'description' => strip_tags((string) $product->description) ?: $product->title,
    'sku' => (string) $product->id,
    'offers' => [
        '@type' => 'Offer',
        'price' => $offer ? $offer->discountedPrice() : $product->price,
        'priceCurrency' => 'TZS',
        'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)]),
        'itemCondition' => $product->condition === 'new' ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition',
        'seller' => ['@type' => 'Organization', 'name' => $seller->shop_name],
    ],
    ...($seller->rating_count > 0 ? [
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => (float) $seller->rating_avg,
            'reviewCount' => $seller->rating_count,
        ],
    ] : []),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

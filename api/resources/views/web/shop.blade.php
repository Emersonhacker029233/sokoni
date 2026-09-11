@extends('layouts.app')

@section('content')
<div class="h-32 bg-gradient-to-r from-sokoni-black to-sokoni-black/80 sm:h-48"></div>

<div class="mx-auto max-w-6xl px-16 lg:px-24">
    <div class="-mt-32 flex flex-col items-center sm:-mt-24 sm:flex-row sm:items-end sm:gap-16">
        @if ($seller->logo)
            <img src="{{ $seller->logo }}" alt="{{ $seller->shop_name }}" class="h-96 w-96 rounded-full border-4 border-white object-cover shadow-sm sm:h-112 sm:w-112">
        @else
            <div class="flex h-96 w-96 items-center justify-center rounded-full border-4 border-white bg-sokoni-surface-alt text-3xl font-bold shadow-sm sm:h-112 sm:w-112">
                {{ strtoupper(substr($seller->shop_name, 0, 1)) }}
            </div>
        @endif
        <div class="mt-12 text-center sm:mt-0 sm:pb-4 sm:text-left">
            <h1 class="flex items-center justify-center gap-6 text-xl font-bold sm:justify-start">
                {{ $seller->shop_name }}
                @if ($seller->isVerified())
                    <span class="badge-verified h-18 w-18"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-full w-full"><path d="M438-452-58-57q-11-11-27.5-11T324-508q-11 11-11 28t11 28l86 86q12 12 28 12t28-12l170-170q12-12 11.5-28T636-592q-12-12-28.5-12.5T579-593L438-452ZM326-90l-58-98-110-24q-15-3-24-15.5t-7-27.5l11-113-75-86q-10-11-10-26t10-26l75-86-11-113q-2-15 7-27.5t24-15.5l110-24 58-98q8-13 22-17.5t28 1.5l104 44 104-44q14-6 28-1.5t22 17.5l58 98 110 24q15 3 24 15.5t7 27.5l-11 113 75 86q10 11 10 26t-10 26l-75 86 11 113q2 15-7 27.5T802-212l-110 24-58 98q-8 13-22 17.5T584-74l-104-44-104 44q-14 6-28 1.5T326-90Z" /></svg></span>
                @endif
            </h1>
            {{-- `@{{ }}` is Blade's escape for a *literal* `{{ }}` in the output (for JS
                 frameworks sharing the same delimiter) — it was never valid here and always
                 printed the raw template text instead of the handle (tester feedback A1).
                 The leading @ is a plain character in the expression itself, not the escape. --}}
            <p class="text-sm text-sokoni-black/50">{{ '@'.$seller->handle }} &middot; {{ $seller->category?->name(app()->getLocale()) }}</p>

            {{-- Full address, prominent in the header — not buried below, per tester feedback. --}}
            <p class="mt-8 flex items-center justify-center gap-4 text-sm text-sokoni-black/70 sm:justify-start">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-14 w-14 shrink-0 text-sokoni-black/40"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" /></svg>
                {{ $seller->address }}, {{ $seller->district }}, {{ $seller->region }}
            </p>
        </div>
    </div>

    {{-- Counts — everything a buyer needs to trust the seller, at a glance. --}}
    <div class="mt-24 flex flex-wrap justify-center gap-32 border-b border-sokoni-outline pb-24 text-center sm:justify-start">
        <div><p class="text-lg font-bold">{{ $seller->products()->visible()->count() }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_listings') }}</p></div>
        <div><p class="text-lg font-bold">{{ $seller->customer_count }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_customers') }}</p></div>
        <div class="flex flex-col items-center sm:items-start"><x-star-rating :rating="(float) $seller->rating_avg" /><p class="text-xs text-sokoni-black/50">({{ $seller->rating_count }})</p></div>
        <div><p class="text-lg font-bold">{{ $seller->created_at->format('Y') }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_member_since') }}</p></div>
    </div>

    {{-- Bio + contact --}}
    <div class="mt-24 grid gap-24 md:grid-cols-3">
        <div class="md:col-span-2">
            @if ($seller->localizedBio(app()->getLocale()))
                <h2 class="text-sm font-semibold text-sokoni-black/50">{{ __('site.shop_about') }}</h2>
                <p class="mt-4 text-sm text-sokoni-black/80">{{ $seller->localizedBio(app()->getLocale()) }}</p>
            @endif

            @if ($seller->hasLocation())
                {{-- OpenStreetMap embed — no API key, no billing (Google Maps couldn't be set up on this
                     account). Lazy-loaded so it costs nothing on 3G until actually scrolled into view.
                     A small, fixed bounding box around the shop's own point, not a wide city view. --}}
                @php
                    $boxSize = 0.008;
                    $bbox = ($seller->lng - $boxSize).','.($seller->lat - $boxSize).','.($seller->lng + $boxSize).','.($seller->lat + $boxSize);
                @endphp
                <div class="mt-16 overflow-hidden rounded-card border border-sokoni-outline">
                    <iframe
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $seller->lat }},{{ $seller->lng }}"
                        loading="lazy"
                        class="h-[220px] w-full border-0"
                        title="{{ __('site.shop_map_title', ['shop' => $seller->shop_name]) }}"
                    ></iframe>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-12 text-sm">
                    <a href="geo:{{ $seller->lat }},{{ $seller->lng }}?q={{ $seller->lat }},{{ $seller->lng }}({{ urlencode($seller->shop_name) }})" class="inline-flex items-center gap-4 font-medium text-sokoni-black/70 hover:underline">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" /></svg>
                        {{ __('site.shop_directions') }}
                    </a>
                    {{-- geo: URIs aren't handled everywhere (notably desktop browsers, iOS Safari with no maps app registered) — a plain web link always works as the fallback. --}}
                    <a href="https://www.openstreetmap.org/directions?to={{ $seller->lat }}%2C{{ $seller->lng }}" target="_blank" rel="noopener" class="text-sokoni-black/40 hover:underline">
                        {{ __('site.shop_directions_web') }}
                    </a>
                </div>
            @endif

            <div class="mt-16 flex gap-8">
                {{-- Same fix as the product page (tester feedback A2) — starts/resumes a
                     real conversation with this seller instead of the dead link to the
                     conversation list. No product context here (shop-level, not a
                     specific listing), so product_id is simply omitted. --}}
                @auth('web')
                    <form action="{{ route('web.account.messages.start') }}" method="post">
                        @csrf
                        <input type="hidden" name="seller_id" value="{{ $seller->id }}">
                        <button type="submit" class="btn-secondary text-sm">{{ __('site.product_message') }}</button>
                    </form>
                @else
                    <a href="{{ route('web.login') }}" class="btn-secondary text-sm">{{ __('site.product_message') }}</a>
                @endauth
                @if ($seller->show_whatsapp && $seller->whatsapp)
                    <a href="https://wa.me/{{ ltrim($seller->whatsapp, '+') }}" target="_blank" rel="noopener" class="btn-secondary text-sm">{{ __('site.product_whatsapp') }}</a>
                @endif
            </div>
            <div class="mt-8">
                <x-report-button type="shop" :id="$seller->id" />
            </div>
        </div>

        {{-- Opening hours --}}
        <div class="card p-16">
            <h2 class="text-sm font-semibold">{{ __('site.shop_hours') }}</h2>
            <dl class="mt-8 space-y-4 text-sm">
                @foreach ($openingHours as $day => $hours)
                    <div class="flex justify-between">
                        <dt class="text-sokoni-black/60">{{ __('site.day_'.$day) }}</dt>
                        <dd>{{ $hours ? "{$hours['open']} - {$hours['close']}" : __('site.shop_hours_closed') }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mt-32 border-b border-sokoni-outline">
        <nav class="flex gap-24 text-sm font-medium">
            <a href="?tab=listings" class="border-b-2 py-12 {{ $tab === 'listings' ? 'border-sokoni-black text-sokoni-black' : 'border-transparent text-sokoni-black/40' }}">{{ __('site.shop_listings') }}</a>
            <a href="?tab=gallery" class="border-b-2 py-12 {{ $tab === 'gallery' ? 'border-sokoni-black text-sokoni-black' : 'border-transparent text-sokoni-black/40' }}">{{ __('site.shop_gallery') }}</a>
            <a href="?tab=reviews" class="border-b-2 py-12 {{ $tab === 'reviews' ? 'border-sokoni-black text-sokoni-black' : 'border-transparent text-sokoni-black/40' }}">{{ __('site.shop_reviews') }}</a>
        </nav>
    </div>

    <div class="py-24">
        @if ($tab === 'listings')
            @if ($products->isEmpty())
                @include('web.partials.empty-results')
            @else
                <div class="grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4 lg:gap-24">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                {{ $products->links() }}
            @endif
        @elseif ($tab === 'gallery')
            <div class="grid grid-cols-3 gap-8">
                @foreach ($showcases as $showcase)
                    <div class="relative aspect-[9/16] overflow-hidden rounded-chip bg-sokoni-surface-alt">
                        <img src="{{ $showcase->thumb_path }}" alt="{{ $showcase->caption }}" loading="lazy" class="h-full w-full object-cover">
                        <span class="absolute bottom-4 right-4 rounded-full bg-black/60 px-6 py-2 text-xs text-white">{{ $showcase->views }}</span>
                    </div>
                @endforeach
                @foreach ($updates as $update)
                    <div class="relative aspect-square overflow-hidden rounded-chip bg-sokoni-surface-alt">
                        <img src="{{ $update->thumb_path ?? $update->media_path }}" alt="{{ $update->caption }}" loading="lazy" class="h-full w-full object-cover">
                    </div>
                @endforeach
                @if ($showcases->isEmpty() && $updates->isEmpty())
                    <p class="col-span-3 py-24 text-center text-sm text-sokoni-black/50">No gallery items yet.</p>
                @endif
            </div>
        @else
            <div class="mb-24 flex gap-16">
                @foreach (range(5, 1) as $star)
                    <div class="flex items-center gap-8 text-sm">
                        <span>{{ $star }}★</span>
                        <div class="h-6 w-24 rounded-full bg-sokoni-surface-alt">
                            <div class="h-6 rounded-full bg-sokoni-yellow" style="width: {{ $seller->rating_count > 0 ? (($distribution[$star] ?? 0) / $seller->rating_count * 100) : 0 }}%"></div>
                        </div>
                        <span class="text-sokoni-black/40">{{ $distribution[$star] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>

            {{-- A1 (tester feedback): this tab used to give zero indication
                 either way — a buyer with a qualifying order and one without
                 saw the exact same blank list, both reading as "broken".
                 The actual review form lives on the order page (it needs a
                 specific order to bind to), so this is a link there, or the
                 honest reason it's not available yet. --}}
            @auth('web')
                @if ($reviewableOrder)
                    <div class="mb-24 rounded-card border border-sokoni-outline bg-sokoni-surface-alt p-16">
                        <p class="text-sm font-medium">{{ __('site.shop_reviewable_prompt') }}</p>
                        <a href="{{ route('web.account.orders.show', $reviewableOrder) }}" class="btn-primary mt-12 inline-flex px-16 py-10 text-sm">
                            {{ __('site.order_leave_review') }}
                        </a>
                    </div>
                @else
                    <p class="mb-24 rounded-card border border-sokoni-outline bg-sokoni-surface-alt p-16 text-sm text-sokoni-black/60">
                        {{ __('site.shop_review_gate_explanation') }}
                    </p>
                @endif
            @else
                <p class="mb-24 rounded-card border border-sokoni-outline bg-sokoni-surface-alt p-16 text-sm text-sokoni-black/60">
                    {{ __('site.shop_review_gate_explanation') }}
                </p>
            @endauth

            @forelse ($reviews as $review)
                <div class="border-b border-sokoni-outline py-16">
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
            @empty
                <p class="text-sm text-sokoni-black/50">{{ __('site.product_no_reviews') }}</p>
            @endforelse
            {{ $reviews?->links() }}
        @endif
    </div>
</div>
@endsection

@push('head')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $seller->shop_name,
    'image' => $seller->logo,
    'description' => $seller->localizedBio(app()->getLocale()),
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $seller->address,
        'addressLocality' => $seller->district,
        'addressRegion' => $seller->region,
        'addressCountry' => 'TZ',
    ],
    ...($seller->hasLocation() ? ['geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $seller->lat, 'longitude' => (float) $seller->lng]] : []),
    'telephone' => $seller->show_whatsapp ? $seller->whatsapp : null,
    'openingHoursSpecification' => \App\Support\OpeningHours::toSchemaOrg($seller->opening_hours),
    ...($seller->rating_count > 0 ? ['aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => (float) $seller->rating_avg, 'reviewCount' => $seller->rating_count]] : []),
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

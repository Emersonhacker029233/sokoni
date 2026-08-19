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
                    <span class="badge-verified h-18 w-18"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-12 w-12"><path fill-rule="evenodd" d="M10 1l2.39 1.36L15 2l.61 2.61L18 5.99l-1.36 2.39L18 10.77 15.61 12l-.61 2.61L12 14l-2 1.99L8 14l-2.61.61L5 12 2.39 10.77 4 8.38 2.39 5.99 5 5l.39-2.39L8 2z" clip-rule="evenodd" /></svg></span>
                @endif
            </h1>
            <p class="text-sm text-sokoni-black/50">@{{ $seller->handle }} &middot; {{ $seller->category?->name(app()->getLocale()) }}</p>
        </div>
    </div>

    {{-- Counts --}}
    <div class="mt-24 flex justify-center gap-32 border-b border-sokoni-outline pb-24 text-center sm:justify-start">
        <div><p class="text-lg font-bold">{{ $seller->products()->visible()->count() }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_listings') }}</p></div>
        <div><p class="text-lg font-bold">{{ $seller->customer_count }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_customers') }}</p></div>
        <div class="flex flex-col items-center sm:items-start"><x-star-rating :rating="(float) $seller->rating_avg" /><p class="text-xs text-sokoni-black/50">({{ $seller->rating_count }})</p></div>
    </div>

    {{-- Bio + contact --}}
    <div class="mt-24 grid gap-24 md:grid-cols-3">
        <div class="md:col-span-2">
            @if ($seller->bio)
                <h2 class="text-sm font-semibold text-sokoni-black/50">{{ __('site.shop_about') }}</h2>
                <p class="mt-4 text-sm text-sokoni-black/80">{{ $seller->bio }}</p>
            @endif
            <p class="mt-12 text-sm text-sokoni-black/70">{{ $seller->address }}, {{ $seller->district }}, {{ $seller->region }}</p>
            @if ($seller->hasLocation())
                <a href="https://www.google.com/maps?q={{ $seller->lat }},{{ $seller->lng }}" target="_blank" rel="noopener" class="mt-8 inline-flex items-center gap-4 text-sm font-medium text-sokoni-black/70 hover:underline">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" /></svg>
                    {{ __('site.shop_directions') }}
                </a>
            @endif

            <div class="mt-16 flex gap-8">
                <a href="{{ auth('web')->check() ? route('web.account.messages') : route('web.login') }}" class="btn-secondary text-sm">{{ __('site.product_message') }}</a>
                @if ($seller->show_whatsapp && $seller->whatsapp)
                    <a href="https://wa.me/{{ ltrim($seller->whatsapp, '+') }}" target="_blank" rel="noopener" class="btn-secondary text-sm">{{ __('site.product_whatsapp') }}</a>
                @endif
            </div>
        </div>

        {{-- Opening hours --}}
        <div class="card p-16">
            <h2 class="text-sm font-semibold">{{ __('site.shop_hours') }}</h2>
            <dl class="mt-8 space-y-4 text-sm">
                @foreach ($openingHours as $day => $hours)
                    <div class="flex justify-between">
                        <dt class="capitalize text-sokoni-black/60">{{ $day }}</dt>
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
                <div class="grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4">
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
    'description' => $seller->bio,
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

@php
    $activeChips = [];
    if (request('price_min') || request('price_max')) {
        $activeChips[] = [
            'label' => \App\Support\Money::format((int) request('price_min', 0)).' - '.(request('price_max') ? \App\Support\Money::format((int) request('price_max')) : '∞'),
            'href' => request()->fullUrlWithoutQuery(['price_min', 'price_max']),
        ];
    }
    if (request('condition')) {
        $activeChips[] = [
            'label' => request('condition') === 'new' ? __('site.filter_condition_new') : __('site.filter_condition_used'),
            'href' => request()->fullUrlWithoutQuery(['condition']),
        ];
    }
    if (request('region')) {
        $activeChips[] = [
            'label' => \App\Support\TanzaniaRegions::fromSlug(request('region')),
            'href' => request()->fullUrlWithoutQuery(['region']),
        ];
    }
    if (request()->boolean('has_video')) {
        $activeChips[] = ['label' => __('site.filter_video'), 'href' => request()->fullUrlWithoutQuery(['has_video'])];
    }
    if (request()->boolean('sponsored')) {
        $activeChips[] = ['label' => __('site.filter_sponsored'), 'href' => request()->fullUrlWithoutQuery(['sponsored'])];
    }
@endphp

@if (! empty($activeChips))
    <div class="mb-16 flex flex-wrap gap-8">
        @foreach ($activeChips as $chip)
            <x-category-chip :href="$chip['href']" active removable>{{ $chip['label'] }}</x-category-chip>
        @endforeach
    </div>
@endif

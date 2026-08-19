<form method="get" class="flex items-center gap-8 text-sm" x-data="{ submit(e) { e.target.form.submit() } }">
    @foreach (request()->except('sort', 'page') as $key => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <label for="sort-select" class="text-sokoni-black/50">{{ __('site.sort_label') }}</label>
    <select id="sort-select" name="sort" onchange="this.form.submit()" class="input-field w-auto py-8">
        <option value="newest" @selected(request('sort', 'newest') === 'newest')>{{ __('site.sort_newest') }}</option>
        <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('site.sort_price_asc') }}</option>
        <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('site.sort_price_desc') }}</option>
        <option value="nearby" @selected(request('sort') === 'nearby')>{{ __('site.sort_nearby') }}</option>
        <option value="trending" @selected(request('sort') === 'trending')>{{ __('site.sort_trending') }}</option>
    </select>
</form>

{{-- Shared by category.blade.php and search.blade.php — all current query params are preserved as hidden inputs so a filter change doesn't drop q/region/etc. --}}
<form
    action="{{ $action }}"
    method="get"
    class="space-y-20 rounded-card border border-sokoni-outline p-16"
    x-data="{ make: {{ Illuminate\Support\Js::from(request('make', '')) }}, vehicleMakeModels: {{ Illuminate\Support\Js::from($vehicleMakeModels ?? []) }} }"
>
    @foreach (request()->except(['price_min', 'price_max', 'condition', 'region', 'has_video', 'sponsored', 'make', 'model', 'year', 'page']) as $key => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    {{-- C3 (tester feedback): Cars category-page filters — only rendered
         when browsing the Cars category itself, never on other category
         or search pages. --}}
    @if ($showVehicleFilters ?? false)
        <div>
            <h3 class="text-sm font-semibold">{{ __('site.product_form_make') }}</h3>
            <select name="make" x-model="make" @change="$el.form.model.value = ''" class="input-field mt-8 text-sm">
                <option value="">{{ __('site.filter_any_make') }}</option>
                @foreach ($vehicleMakes ?? [] as $makeName)
                    <option value="{{ $makeName }}" @selected(request('make') === $makeName)>{{ $makeName }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="make">
            <h3 class="text-sm font-semibold">{{ __('site.product_form_model') }}</h3>
            <select name="model" class="input-field mt-8 text-sm">
                <option value="">{{ __('site.filter_any_model') }}</option>
                <template x-for="modelName in (vehicleMakeModels[make] || [])" :key="modelName">
                    <option :value="modelName" :selected="modelName === {{ Illuminate\Support\Js::from(request('model', '')) }}" x-text="modelName"></option>
                </template>
            </select>
        </div>
        {{-- C4 (tester feedback): Year — a flat 1990-current range (see
             DECISIONS.md), not narrowed by make/model, so it's independent
             of both and always available once browsing Cars. --}}
        <div>
            <h3 class="text-sm font-semibold">{{ __('site.product_form_year') }}</h3>
            <select name="year" class="input-field mt-8 text-sm">
                <option value="">{{ __('site.filter_any_year') }}</option>
                @foreach ($vehicleYears ?? [] as $yearOption)
                    <option value="{{ $yearOption }}" @selected((string) request('year') === (string) $yearOption)>{{ $yearOption }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <h3 class="text-sm font-semibold">{{ __('site.filter_price') }}</h3>
        <div class="mt-8 flex items-center gap-8">
            <input type="number" name="price_min" value="{{ request('price_min') }}" placeholder="{{ __('site.filter_price_min') }}" class="input-field text-sm">
            <span class="text-sokoni-black/40">–</span>
            <input type="number" name="price_max" value="{{ request('price_max') }}" placeholder="{{ __('site.filter_price_max') }}" class="input-field text-sm">
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold">{{ __('site.filter_condition') }}</h3>
        <div class="mt-8 space-y-4">
            @foreach (['new' => __('site.filter_condition_new'), 'used' => __('site.filter_condition_used')] as $value => $label)
                <label class="flex items-center gap-8 text-sm">
                    <input type="radio" name="condition" value="{{ $value }}" @checked(request('condition') === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold">{{ __('site.filter_region') }}</h3>
        <select name="region" class="input-field mt-8 text-sm">
            <option value="">{{ __('site.search_region_all') }}</option>
            @foreach ($regions as $slug => $name)
                <option value="{{ $slug }}" @selected(request('region') === $slug)>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div class="space-y-8">
        <label class="flex items-center gap-8 text-sm">
            <input type="checkbox" checked disabled class="opacity-60">
            {{ __('site.filter_verified') }}
        </label>
        <p class="text-xs text-sokoni-black/40">{{ __('site.filter_verified_hint') }}</p>

        <label class="flex items-center gap-8 text-sm">
            <input type="checkbox" name="has_video" value="1" @checked(request()->boolean('has_video'))>
            {{ __('site.filter_video') }}
        </label>
        <label class="flex items-center gap-8 text-sm">
            <input type="checkbox" name="sponsored" value="1" @checked(request()->boolean('sponsored'))>
            {{ __('site.filter_sponsored') }}
        </label>
    </div>

    <div class="flex gap-8">
        <button type="submit" class="btn-primary flex-1 text-sm">{{ __('site.filter_apply') }}</button>
        <a href="{{ url()->current() }}" class="btn-ghost text-sm">{{ __('site.filter_clear') }}</a>
    </div>
</form>

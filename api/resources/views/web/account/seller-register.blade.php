@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-h2">{{ __('site.seller_register_title') }}</h1>
    <p class="text-body mt-8">{{ __('site.seller_register_intro') }}</p>

    {{-- x-data here only tracks submit-in-progress — a real per-byte progress bar
         needs its own AJAX endpoint per file the way the product photo manager
         has, which doesn't fit a single one-shot registration submission; this
         is the honest, proportionate version for a plain form POST (tester
         feedback A4). --}}
    <form
        action="{{ route('web.account.shop.register.store') }}"
        method="post"
        enctype="multipart/form-data"
        class="mt-32 space-y-40"
        x-data="{ submitting: false }"
        @submit="submitting = true"
    >
        @csrf

        {{-- Section 1: business details --}}
        <section>
            <h2 class="text-h3 border-b border-sokoni-outline pb-8">{{ __('site.seller_section_business') }}</h2>
            <div class="mt-16 space-y-16">
                <div>
                    <label for="shop_name" class="text-sm font-medium">{{ __('site.seller_shop_name') }}</label>
                    <input type="text" id="shop_name" name="shop_name" value="{{ old('shop_name') }}" required class="input-field mt-4">
                    @error('shop_name') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="handle" class="text-sm font-medium">{{ __('site.seller_handle') }}</label>
                    <div class="mt-4 flex items-stretch overflow-hidden rounded-chip border border-sokoni-outline">
                        <span class="flex items-center bg-sokoni-surface-alt px-12 text-sm text-sokoni-black/50">sokoni.co.tz/@</span>
                        <input type="text" id="handle" name="handle" value="{{ old('handle') }}" required pattern="[a-z0-9_]{3,20}" class="min-w-0 flex-1 border-none px-12 py-12 text-sm focus:outline-none focus:ring-0">
                    </div>
                    <p class="mt-4 text-xs text-sokoni-black/40">{{ __('site.seller_handle_hint') }}</p>
                    @error('handle') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="category_id" class="text-sm font-medium">{{ __('site.seller_category') }}</label>
                    <select id="category_id" name="category_id" required class="input-field mt-4">
                        <option value="">{{ __('site.seller_category_placeholder') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name(app()->getLocale()) }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="bio" class="text-sm font-medium">{{ __('site.seller_description') }}</label>
                    <textarea id="bio" name="bio" rows="3" class="input-field mt-4">{{ old('bio') }}</textarea>
                    @error('bio') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="whatsapp" class="text-sm font-medium">{{ __('site.seller_whatsapp') }}</label>
                    <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+255700000000" class="input-field mt-4">
                    @error('whatsapp') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Section 2: location — no interactive map (no Google Maps key configured; see DECISIONS.md),
             region/district/address plus best-effort geocoding instead: "Use my current location" below
             reverse-geocodes through Nominatim client-side to prefill these same fields (tester feedback
             item 7), and SellerRegistrationController still geocodes the typed address server-side as a
             fallback for anyone who never uses the button. Either way every field stays a plain, always-
             editable input — a denied permission or a failed lookup never blocks manual entry. --}}
        <section
            x-data="sellerLocationPicker(@js(array_values($regions)))"
            x-init="region = @js(old('region', '')); district = @js(old('district', '')); address = @js(old('address', '')); lat = @js(old('lat', '')); lng = @js(old('lng', ''))"
        >
            <h2 class="text-h3 border-b border-sokoni-outline pb-8">{{ __('site.seller_section_location') }}</h2>
            <p class="mt-8 text-xs text-sokoni-black/50">{{ __('site.seller_location_hint') }}</p>

            <button type="button" @click="useCurrentLocation()" :disabled="locating" class="btn-secondary mt-12 disabled:opacity-60">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16 text-sokoni-yellow"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" /></svg>
                <span x-show="!locating">{{ __('site.seller_use_current_location') }}</span>
                <span x-show="locating" x-cloak>{{ __('site.seller_locating') }}</span>
            </button>
            <p x-show="statusMessage" x-cloak class="mt-8 text-xs text-sokoni-black/60" x-text="statusMessage"></p>

            <input type="hidden" name="lat" x-model="lat">
            <input type="hidden" name="lng" x-model="lng">

            <div class="mt-16 grid gap-16 sm:grid-cols-2">
                <div>
                    <label for="region" class="text-sm font-medium">{{ __('site.seller_region') }}</label>
                    <select id="region" name="region" x-model="region" required class="input-field mt-4">
                        <option value="">{{ __('site.seller_region_placeholder') }}</option>
                        @foreach ($regions as $slug => $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('region') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="district" class="text-sm font-medium">{{ __('site.seller_district') }}</label>
                    <input type="text" id="district" name="district" x-model="district" required class="input-field mt-4">
                    @error('district') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-16">
                <label for="address" class="text-sm font-medium">{{ __('site.seller_address') }}</label>
                <input type="text" id="address" name="address" x-model="address" required class="input-field mt-4">
                @error('address') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Section 3: identity --}}
        <section>
            <h2 class="text-h3 border-b border-sokoni-outline pb-8">{{ __('site.seller_section_identity') }}</h2>
            <div class="mt-16 space-y-16">
                <div>
                    <label for="nida_number" class="text-sm font-medium">{{ __('site.seller_nida_number') }}</label>
                    <input type="text" id="nida_number" name="nida_number" value="{{ old('nida_number') }}" required inputmode="numeric" pattern="\d{20}" class="input-field mt-4">
                    <p class="mt-4 text-xs text-sokoni-black/40">{{ __('site.seller_nida_hint') }}</p>
                    @error('nida_number') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ fileName: '' }">
                    <label for="nida_image" class="text-sm font-medium">{{ __('site.seller_nida_image') }}</label>
                    <input type="file" id="nida_image" name="nida_image" accept="image/*" required class="input-field mt-4" @change="fileName = $event.target.files[0]?.name ?? ''">
                    <p x-show="fileName" x-cloak class="mt-4 flex items-center gap-4 text-xs text-sokoni-success">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-14 w-14"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        <span x-text="fileName"></span>
                    </p>
                    @error('nida_image') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Section 4: licence --}}
        <section>
            <h2 class="text-h3 border-b border-sokoni-outline pb-8">{{ __('site.seller_section_licence') }}</h2>
            <div class="mt-16" x-data="{ fileName: '' }">
                <label for="licence_file" class="text-sm font-medium">{{ __('site.seller_licence_file') }} <span class="font-normal text-sokoni-black/40">({{ __('site.seller_optional') }})</span></label>
                <input type="file" id="licence_file" name="licence_file" accept="image/*,.pdf" class="input-field mt-4" @change="fileName = $event.target.files[0]?.name ?? ''">
                <p x-show="fileName" x-cloak class="mt-4 flex items-center gap-4 text-xs text-sokoni-success">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-14 w-14"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                    <span x-text="fileName"></span>
                </p>
                <p class="mt-4 text-xs text-sokoni-black/40">{{ __('site.seller_licence_hint') }}</p>
                @error('licence_file') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
            </div>
        </section>

        <button type="submit" class="btn-primary w-full py-14 text-base" :disabled="submitting" :class="submitting ? 'opacity-60' : ''">
            <span x-show="!submitting">{{ __('site.seller_submit') }}</span>
            <span x-show="submitting" x-cloak>{{ __('site.seller_submitting') }}</span>
        </button>
    </form>
</div>
@endsection

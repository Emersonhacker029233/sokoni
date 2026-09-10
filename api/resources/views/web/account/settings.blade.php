@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.account_settings') }}</h1>

    {{-- Status confirmation now renders globally via partials.flash. --}}

    @if ($seller)
        {{-- Sellers had no way to change their shop logo from the website at all
             (tester feedback A5) — AJAX upload with compression and an immediate
             preview swap, same pattern as the product photo manager. --}}
        <div class="mt-24 rounded-card border border-sokoni-outline p-16" x-data="shopLogoUploader({{ $seller->id }}, @json($seller->logo))">
            <label class="text-sm font-medium">{{ __('site.account_shop_logo') }}</label>
            <div class="mt-8 flex items-center gap-16">
                <div class="relative h-64 w-64 shrink-0 overflow-hidden rounded-full bg-sokoni-surface-alt">
                    <img x-show="logoUrl" :src="logoUrl" alt="" class="h-full w-full object-cover">
                    <div x-show="!logoUrl" class="flex h-full w-full items-center justify-center text-lg font-bold">{{ strtoupper(substr($seller->shop_name, 0, 1)) }}</div>
                    {{-- A3 (tester feedback): this used to be an indeterminate
                         spinner only — no percentage at all, unlike the
                         product photo manager's own real per-file progress.
                         Now shows the same real XHR upload progress. --}}
                    <div x-show="uploading" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/40">
                        <span class="text-xs font-semibold text-white" x-text="progress + '%'"></span>
                    </div>
                </div>
                <label class="btn-secondary cursor-pointer text-sm">
                    {{ __('site.account_shop_logo_change') }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFileSelected($event.target.files); $event.target.value = ''">
                </label>
            </div>
            <p x-show="error" x-cloak class="mt-8 text-xs text-sokoni-danger" x-text="error"></p>
        </div>
    @endif

    <form action="{{ route('web.account.settings.update') }}" method="post" class="mt-24 space-y-16">
        @csrf
        <div>
            <label for="name" class="text-sm font-medium">{{ __('site.auth_name_label') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="input-field mt-4">
            @error('name') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="text-sm font-medium">{{ __('site.account_email_label') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input-field mt-4" placeholder="{{ __('site.account_email_hint') }}">
            @error('email') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
            @if ($user->email)
                <p class="mt-4 text-xs {{ $user->hasVerifiedEmail() ? 'text-sokoni-success' : 'text-sokoni-black/50' }}">
                    {{ $user->hasVerifiedEmail() ? __('site.account_email_verified') : __('site.account_email_unverified') }}
                </p>
            @endif
        </div>
        <div>
            <label for="locale" class="text-sm font-medium">{{ __('site.account_language_label') }}</label>
            <select id="locale" name="locale" class="input-field mt-4">
                <option value="en" @selected($user->locale === 'en')>English</option>
                <option value="sw" @selected($user->locale === 'sw')>Kiswahili</option>
            </select>
        </div>
        <button type="submit" class="btn-primary w-full py-12">{{ __('site.account_save_changes') }}</button>
    </form>

    @if ($user->email && ! $user->hasVerifiedEmail())
        <form action="{{ route('web.account.settings.resend-verification') }}" method="post" class="mt-12">
            @csrf
            <button type="submit" class="text-sm font-medium text-sokoni-black/70 underline">{{ __('site.account_resend_verification') }}</button>
        </form>
    @endif
</div>
@endsection

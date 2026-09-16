@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-48">
    <h1 class="text-xl font-bold">{{ __('site.nav_sign_in') }}</h1>
    <p class="mt-4 text-sm text-sokoni-black/60">Sign in or create an account — it only takes a phone number.</p>

    @if ($errors->any())
        <div class="mt-16 rounded-chip bg-sokoni-danger/10 p-12 text-sm text-sokoni-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($step === 'phone')
        <form action="{{ route('web.auth.otp.request') }}" method="post" class="mt-24 space-y-16">
            @csrf
            {{-- Part 2 (client feedback): "+255 shown as a fixed,
                 non-editable prefix — the user types only their own
                 number." The visible field only ever holds local digits;
                 Alpine composes the full E.164 value into the hidden
                 `phone` field the server actually validates. --}}
            <div x-data="phoneInput()">
                <label for="phone_local" class="text-sm font-medium">{{ __('site.auth_phone_label') }}</label>
                <div class="mt-4 flex items-stretch overflow-hidden rounded-chip border border-sokoni-outline focus-within:ring-2 focus-within:ring-sokoni-yellow">
                    <span class="flex items-center bg-sokoni-surface-alt px-12 text-sm font-medium text-sokoni-black/70" aria-hidden="true">+255</span>
                    <input
                        type="tel"
                        id="phone_local"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        placeholder="712 345 678"
                        required
                        x-model="local"
                        class="input-field flex-1 rounded-none border-none"
                    >
                </div>
                <input type="hidden" name="phone" :value="e164">
            </div>
            <button type="submit" class="btn-primary w-full py-12">{{ __('site.auth_send_code') }}</button>
        </form>
    @else
        <form action="{{ route('web.auth.otp.verify') }}" method="post" class="mt-24 space-y-16">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}">
            <p class="text-sm text-sokoni-black/60">Code sent to {{ $phone }}.</p>
            @if ($isNewAccount)
                <p class="rounded-chip bg-sokoni-surface-alt p-8 text-xs text-sokoni-black/60">This number is new to Sokoni — verifying the code will create your account.</p>
            @endif
            <div>
                <label for="code" class="text-sm font-medium">{{ __('site.auth_code_label') }}</label>
                <input type="text" id="code" name="code" maxlength="6" required class="input-field mt-4">
            </div>
            @if ($isNewAccount)
                <div>
                    <label for="name" class="text-sm font-medium">{{ __('site.auth_name_label') }}</label>
                    <input type="text" id="name" name="name" required class="input-field mt-4">
                </div>
                <label class="flex items-start gap-8 text-xs text-sokoni-black/60">
                    <input type="checkbox" name="marketing_consent" value="1" class="mt-2">
                    {{ __('site.auth_marketing_consent') }}
                </label>
            @endif
            <button type="submit" class="btn-primary w-full py-12">{{ __('site.auth_verify') }}</button>
        </form>
    @endif

    @if ($googleConfigured)
        <div class="mt-24 flex items-center gap-12 text-xs text-sokoni-black/40">
            <div class="h-px flex-1 bg-sokoni-outline"></div>{{ __('site.auth_or') }}<div class="h-px flex-1 bg-sokoni-outline"></div>
        </div>
        <a href="{{ route('web.auth.google.redirect') }}" class="btn-secondary mt-16 flex w-full py-12">{{ __('site.auth_google') }}</a>
    @endif

    <p class="mt-24 text-center text-xs text-sokoni-black/40">
        {{ __('site.auth_terms_prompt') }}
        <a href="{{ route('web.terms') }}" class="underline">{{ __('site.auth_terms') }}</a>
        {{ __('site.auth_and') }}
        <a href="{{ route('web.privacy') }}" class="underline">{{ __('site.auth_privacy') }}</a>.
    </p>
</div>
@endsection

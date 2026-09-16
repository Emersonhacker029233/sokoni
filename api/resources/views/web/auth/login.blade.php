@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-48">
    <h1 class="text-xl font-bold">{{ $addingAccount ? __('site.auth_add_account_title') : __('site.nav_sign_in') }}</h1>
    <p class="mt-4 text-sm text-sokoni-black/60">
        {{ $addingAccount ? __('site.auth_add_account_body') : 'Sign in or create an account — it only takes a phone number.' }}
    </p>

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
        @if ($otpJustResent)
            <p class="mt-16 rounded-chip bg-sokoni-success/10 p-8 text-xs text-sokoni-success">{{ __('site.auth_code_resent') }}</p>
        @endif
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
                {{-- Part 3 (client feedback): "show when the current code
                     expires, so the user understands why it stopped
                     working." A real server timestamp (PhoneOtpService's
                     own TTL), not a guessed duration. --}}
                @if ($otpExpiresAt)
                    <p class="mt-4 text-xs text-sokoni-black/40">
                        {{ __('site.auth_code_expires_at', ['time' => \Illuminate\Support\Carbon::parse($otpExpiresAt)->format('H:i')]) }}
                    </p>
                @endif
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

        {{-- Part 3 (client feedback): "Resend code" — a countdown before
             it becomes available, then a real resend without leaving
             this screen. Posts to the exact same web.auth.otp.request
             route the phone step's own form uses:
             OtpAuthController::requestOtp() tells a resend apart from a
             fresh request by comparing against the phone already in
             session, and redirects straight back to this same code
             step either way. --}}
        <div
            x-data="{ remaining: 60 }"
            x-init="setInterval(() => { if (remaining > 0) remaining--; }, 1000)"
            class="mt-16 text-center text-sm text-sokoni-black/60"
        >
            <span x-show="remaining > 0" x-text="'{{ __('site.auth_resend_in') }} ' + remaining + 's'"></span>
            <form x-show="remaining === 0" x-cloak action="{{ route('web.auth.otp.request') }}" method="post">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <button type="submit" class="font-medium text-sokoni-black underline">{{ __('site.auth_resend_code') }}</button>
            </form>
        </div>
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

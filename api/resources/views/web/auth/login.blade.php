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
            <div>
                <label for="phone" class="text-sm font-medium">{{ __('site.auth_phone_label') }}</label>
                <input type="tel" id="phone" name="phone" placeholder="+255754123456" required class="input-field mt-4">
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

@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-48">
    <h1 class="text-xl font-bold">{{ __('site.legal_acceptance_title') }}</h1>
    <p class="mt-8 text-sm text-sokoni-black/60">{{ __('site.legal_acceptance_body') }}</p>

    <div class="mt-16 flex flex-col gap-8">
        <a href="{{ route('web.terms') }}" target="_blank" class="btn-secondary text-sm">{{ __('site.auth_terms') }}</a>
        <a href="{{ route('web.privacy') }}" target="_blank" class="btn-secondary text-sm">{{ __('site.auth_privacy') }}</a>
    </div>

    <form action="{{ route('web.auth.terms.store') }}" method="post" class="mt-24">
        @csrf
        <label class="flex items-start gap-8 text-sm">
            <input type="checkbox" name="accepted" value="1" required class="mt-2">
            {{ __('site.legal_accept_checkbox', ['version' => \App\Support\Legal::TERMS_VERSION]) }}
        </label>
        @error('accepted')
            <p class="mt-8 text-sm text-sokoni-danger">{{ $message }}</p>
        @enderror
        <button type="submit" class="btn-primary mt-20 w-full py-12">{{ __('site.legal_continue') }}</button>
    </form>
</div>
@endsection

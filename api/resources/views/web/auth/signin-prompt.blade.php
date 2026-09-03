@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-64 text-center">
    <span class="mx-auto flex h-56 w-56 items-center justify-center rounded-full bg-sokoni-yellow/20">
        @if ($feature === 'chats')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-28 w-28"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.24 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-28 w-28"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.964 0a9 9 0 10-11.964 0m11.964 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        @endif
    </span>

    <h1 class="text-h2 mt-24">{{ $feature === 'chats' ? __('site.signin_prompt_chats_title') : __('site.signin_prompt_profile_title') }}</h1>
    <p class="text-body mt-8">{{ $feature === 'chats' ? __('site.signin_prompt_chats_body') : __('site.signin_prompt_profile_body') }}</p>

    <a href="{{ route('web.login', ['redirect' => $feature]) }}" class="btn-primary mt-24 inline-flex px-24 py-14 text-base">
        {{ __('site.nav_sign_in') }}
    </a>
</div>
@endsection

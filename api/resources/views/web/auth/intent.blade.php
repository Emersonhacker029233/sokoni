@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-lg px-16 py-48">
    <h1 class="text-h2 text-center">{{ __('site.auth_intent_title') }}</h1>

    <div class="mt-32 grid gap-16 sm:grid-cols-2">
        <form action="{{ route('web.auth.intent.store') }}" method="post">
            @csrf
            <input type="hidden" name="intent" value="buy">
            <button type="submit" class="product-card flex h-full w-full flex-col items-center gap-12 p-24 text-center">
                <span class="flex h-56 w-56 items-center justify-center rounded-full bg-sokoni-yellow/20">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-28 w-28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.895-4.775 2.253-7.372a1.125 1.125 0 00-1.12-1.28H5.117M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                </span>
                <span class="text-h3">{{ __('site.auth_intent_buy') }}</span>
            </button>
        </form>

        <form action="{{ route('web.auth.intent.store') }}" method="post">
            @csrf
            <input type="hidden" name="intent" value="sell">
            <button type="submit" class="product-card flex h-full w-full flex-col items-center gap-12 p-24 text-center">
                <span class="flex h-56 w-56 items-center justify-center rounded-full bg-sokoni-yellow/20">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-28 w-28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
                </span>
                <span class="text-h3">{{ __('site.auth_intent_sell') }}</span>
            </button>
        </form>
    </div>

    <div class="mt-24 text-center">
        <form action="{{ route('web.auth.intent.store') }}" method="post" class="inline">
            @csrf
            <input type="hidden" name="intent" value="later">
            <button type="submit" class="text-sm text-sokoni-black/50 hover:text-sokoni-black hover:underline">
                {{ __('site.auth_intent_later') }}
            </button>
        </form>
    </div>
</div>
@endsection

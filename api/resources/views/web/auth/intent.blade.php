@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-48">
    <h1 class="text-xl font-bold">{{ __('site.auth_intent_title') }}</h1>

    <div class="mt-24 space-y-12">
        @foreach ([
            'buy' => __('site.auth_intent_buy'),
            'sell' => __('site.auth_intent_sell'),
            'later' => __('site.auth_intent_later'),
        ] as $value => $label)
            <form action="{{ route('web.auth.intent.store') }}" method="post">
                @csrf
                <input type="hidden" name="intent" value="{{ $value }}">
                <button type="submit" class="card flex w-full items-center justify-between p-16 text-left hover:shadow-md">
                    <span class="font-medium">{{ $label }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-16 w-16 text-sokoni-black/30"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                </button>
            </form>
        @endforeach
    </div>
</div>
@endsection

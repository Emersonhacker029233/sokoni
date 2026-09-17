@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.account_messages') }}</h1>

    @forelse ($conversations as $conversation)
        @php($otherName = auth('web')->id() === $conversation->buyer_id ? $conversation->seller->shop_name : $conversation->buyer->name)
        <a href="{{ route('web.account.messages.show', $conversation) }}" class="card mt-8 flex items-center justify-between p-16">
            <div class="min-w-0">
                <p class="truncate font-medium">{{ $otherName }}</p>
                @if ($conversation->product)
                    <p class="truncate text-xs text-sokoni-black/50">{{ __('site.messages_re_prefix', ['title' => $conversation->product->title]) }}</p>
                @endif
                <p class="truncate text-sm text-sokoni-black/50">{{ $conversation->messages->last()?->body ?? __('site.messages_no_messages_yet') }}</p>
            </div>
            <span class="shrink-0 text-xs text-sokoni-black/40">{{ $conversation->last_message_at?->diffForHumans() }}</span>
        </a>
    @empty
        <p class="mt-16 text-sm text-sokoni-black/50">{{ __('site.messages_empty') }}</p>
    @endforelse

    {{ $conversations->links() }}
</div>
@endsection

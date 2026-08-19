@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">{{ $order->code }}</h1>
        <span class="chip">{{ ucfirst($order->status) }}</span>
    </div>
    <p class="text-sm text-sokoni-black/50">{{ $order->seller->shop_name }} &middot; {{ $order->created_at->format('d M Y, H:i') }}</p>

    {{-- Status timeline --}}
    <div class="card mt-24 p-16">
        <h2 class="text-sm font-semibold">Status</h2>
        <ol class="mt-12 space-y-8">
            @foreach ($timeline as $step)
                <li class="flex items-center gap-8 text-sm">
                    <span class="h-8 w-8 rounded-full bg-sokoni-yellow"></span>
                    <span class="font-medium">{{ ucfirst($step['status']) }}</span>
                    <span class="text-sokoni-black/40">{{ $step['at']->format('d M, H:i') }}</span>
                </li>
            @endforeach
        </ol>
        @if ($order->status === 'cancelled' && $order->cancelled_reason)
            <p class="mt-8 text-sm text-sokoni-danger">Reason: {{ $order->cancelled_reason }}</p>
        @endif
    </div>

    {{-- Items --}}
    <div class="card mt-16 p-16">
        <h2 class="text-sm font-semibold">Items</h2>
        <div class="mt-12 space-y-8">
            @foreach ($order->items as $item)
                <div class="flex justify-between text-sm">
                    <span>{{ $item->title_snapshot }} &times; {{ $item->qty }}</span>
                    <span>{{ \App\Support\Money::format($item->lineTotal()) }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-12 flex justify-between border-t border-sokoni-outline pt-12 font-semibold">
            <span>Total</span>
            <span>{{ \App\Support\Money::format($order->total) }}</span>
        </div>
    </div>

    <div class="card mt-16 p-16 text-sm">
        <h2 class="font-semibold">Delivery</h2>
        <p class="mt-8 text-sokoni-black/70">{{ ucfirst($order->delivery_method) }}</p>
        @if ($order->address)
            <p class="text-sokoni-black/70">{{ $order->address }}</p>
        @endif
        @if ($order->notes)
            <p class="mt-8 text-sokoni-black/50">Notes: {{ $order->notes }}</p>
        @endif
    </div>

    @if ($order->review)
        <div class="card mt-16 p-16 text-sm">
            <h2 class="font-semibold">Your review</h2>
            <x-star-rating :rating="(float) $order->review->rating" size="14" />
            @if ($order->review->comment)
                <p class="mt-8 text-sokoni-black/70">{{ $order->review->comment }}</p>
            @endif
        </div>
    @endif
</div>
@endsection

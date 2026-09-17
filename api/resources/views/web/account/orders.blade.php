@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.account_orders') }}</h1>

    @forelse ($orders as $order)
        <a href="{{ route('web.account.orders.show', $order) }}" class="card mt-12 flex items-center justify-between p-16">
            <div>
                <p class="font-medium">{{ $order->code }}</p>
                <p class="text-sm text-sokoni-black/50">{{ $order->seller->shop_name }} &middot; {{ __('site.orders_item_count', ['count' => $order->items->count()]) }}</p>
                <p class="text-xs text-sokoni-black/40">{{ $order->created_at->translatedFormat('d M Y') }}</p>
            </div>
            <div class="text-right">
                <p class="font-semibold">{{ \App\Support\Money::format($order->total) }}</p>
                <span class="chip mt-4 text-xs">{{ $order->statusLabel() }}</span>
            </div>
        </a>
    @empty
        <p class="mt-16 text-sm text-sokoni-black/50">{{ __('site.orders_empty') }} <a href="{{ route('web.home') }}" class="underline">{{ __('site.orders_start_browsing') }}</a>.</p>
    @endforelse

    {{ $orders->links() }}
</div>
@endsection

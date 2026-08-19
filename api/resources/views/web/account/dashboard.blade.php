@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.nav_my_account') }}</h1>
    <p class="text-sm text-sokoni-black/60">{{ auth('web')->user()->name }}</p>

    <div class="mt-24 grid gap-16 sm:grid-cols-2">
        <a href="{{ route('web.account.orders') }}" class="card p-16">
            <p class="text-sm text-sokoni-black/50">{{ __('site.account_orders') }}</p>
            <p class="mt-4 text-2xl font-bold">{{ $recentOrders->count() }}</p>
        </a>
        <a href="{{ route('web.account.saved') }}" class="card p-16">
            <p class="text-sm text-sokoni-black/50">{{ __('site.account_saved') }}</p>
            <p class="mt-4 text-2xl font-bold">{{ $savedCount }}</p>
        </a>
    </div>

    <h2 class="mt-32 font-semibold">{{ __('site.account_orders') }}</h2>
    @forelse ($recentOrders as $order)
        <a href="{{ route('web.account.orders.show', $order) }}" class="card mt-8 flex items-center justify-between p-12">
            <div>
                <p class="text-sm font-medium">{{ $order->code }}</p>
                <p class="text-xs text-sokoni-black/50">{{ $order->seller->shop_name }}</p>
            </div>
            <span class="chip text-xs">{{ ucfirst($order->status) }}</span>
        </a>
    @empty
        <p class="mt-8 text-sm text-sokoni-black/50">No orders yet.</p>
    @endforelse

    @if (! $isSeller)
        <div class="card mt-32 flex items-center justify-between p-16">
            <div>
                <p class="font-medium">{{ __('site.nav_sell') }}</p>
                <p class="text-sm text-sokoni-black/50">Start your own shop on Sokoni.</p>
            </div>
            <a href="{{ route('web.sell') }}" class="btn-primary text-sm">Get started</a>
        </div>
    @endif
</div>
@endsection

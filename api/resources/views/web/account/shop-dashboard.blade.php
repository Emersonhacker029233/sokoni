@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">{{ $seller->shop_name }}</h1>
        <div class="flex gap-8">
            <a href="{{ route('web.shop', $seller->handle) }}" class="btn-secondary text-sm">View shop</a>
            <a href="{{ route('web.account.shop.products.create') }}" class="btn-primary text-sm">+ Add product</a>
        </div>
    </div>
    <p class="text-sm text-sokoni-black/50">
        Status:
        <span class="chip">{{ ucfirst($seller->status) }}</span>
    </p>
    @if ($seller->status === 'pending')
        <p class="mt-8 rounded-chip bg-sokoni-surface-alt p-12 text-sm text-sokoni-black/60">
            Your shop is pending verification. You can keep building it — products stay hidden from public search until you're verified.
        </p>
    @endif

    <div class="mt-24 grid grid-cols-3 gap-16">
        <div class="card p-16 text-center"><p class="text-2xl font-bold">{{ $productsCount }}</p><p class="text-xs text-sokoni-black/50">Products</p></div>
        <div class="card p-16 text-center"><p class="text-2xl font-bold">{{ $stats['total_views'] }}</p><p class="text-xs text-sokoni-black/50">Views</p></div>
        <div class="card p-16 text-center"><p class="text-2xl font-bold">{{ $stats['orders_last_30_days'] }}</p><p class="text-xs text-sokoni-black/50">Orders (30d)</p></div>
    </div>

    <h2 class="mt-32 font-semibold">Recent orders</h2>
    @forelse ($recentOrders as $order)
        <div class="card mt-8 flex items-center justify-between p-12">
            <div>
                <p class="text-sm font-medium">{{ $order->code }}</p>
                <p class="text-xs text-sokoni-black/50">{{ $order->buyer->name }}</p>
            </div>
            <span class="chip text-xs">{{ ucfirst($order->status) }}</span>
        </div>
    @empty
        <p class="mt-8 text-sm text-sokoni-black/50">No orders yet.</p>
    @endforelse

    <a href="{{ route('web.account.shop.products') }}" class="btn-secondary mt-24 inline-flex text-sm">Manage products</a>
</div>
@endsection

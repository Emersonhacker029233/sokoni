@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">{{ $seller->shop_name }}</h1>
        <div class="flex gap-8">
            <a href="{{ route('web.shop', $seller->handle) }}" class="btn-secondary text-sm">{{ __('site.shop_dashboard_view_shop') }}</a>
            <a href="{{ route('web.account.shop.products.create') }}" class="btn-primary text-sm">{{ __('site.shop_dashboard_add_product') }}</a>
        </div>
    </div>
    <p class="text-sm text-sokoni-black/50">
        {{ __('site.shop_dashboard_status_prefix') }}
        <span class="chip">{{ $seller->statusLabel() }}</span>
    </p>
    {{-- Status confirmation now renders globally via partials.flash. --}}

    @if ($seller->status === 'pending')
        <div class="mt-16 rounded-chip bg-sokoni-surface-alt p-16">
            <p class="text-sm font-semibold">{{ __('site.seller_pending_title') }}</p>
            <p class="mt-4 text-sm text-sokoni-black/60">{{ __('site.seller_pending_body') }}</p>
        </div>
    @elseif ($seller->status === 'rejected')
        <div class="mt-16 rounded-chip bg-sokoni-danger/10 p-16">
            <p class="text-sm font-semibold text-sokoni-danger">{{ __('site.seller_rejected_title') }}</p>
            @if ($seller->rejection_reason)
                <p class="mt-4 text-sm text-sokoni-black/60">{{ $seller->rejection_reason }}</p>
            @endif
        </div>
    @endif

    <div class="mt-24 grid grid-cols-3 gap-16">
        <div class="card p-16 text-center"><p class="text-2xl font-bold">{{ $productsCount }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_dashboard_products_stat') }}</p></div>
        <div class="card p-16 text-center"><p class="text-2xl font-bold">{{ $stats['total_views'] }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_dashboard_views_stat') }}</p></div>
        <div class="card p-16 text-center"><p class="text-2xl font-bold">{{ $stats['orders_last_30_days'] }}</p><p class="text-xs text-sokoni-black/50">{{ __('site.shop_dashboard_orders_30d_stat') }}</p></div>
    </div>

    {{-- Sellers had no way to edit opening hours at all (tester feedback A6) —
         only ever displayed, never editable, on either surface. --}}
    <div class="mt-32" x-data="{ editingHours: false }">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ __('site.shop_hours') }}</h2>
            <button type="button" @click="editingHours = !editingHours" class="text-sm font-medium text-sokoni-black/60 hover:underline" x-text="editingHours ? '{{ __('site.report_cancel') }}' : '{{ __('site.shop_hours_edit') }}'"></button>
        </div>

        <dl x-show="!editingHours" class="card mt-8 space-y-4 p-16 text-sm">
            @foreach (\App\Support\OpeningHours::parse($seller->opening_hours) as $day => $hours)
                <div class="flex justify-between">
                    <dt class="text-sokoni-black/60">{{ __('site.day_'.$day) }}</dt>
                    <dd>{{ $hours ? "{$hours['open']} - {$hours['close']}" : __('site.shop_hours_closed') }}</dd>
                </div>
            @endforeach
        </dl>

        <form x-show="editingHours" x-cloak action="{{ route('web.account.shop.hours', $seller) }}" method="post" class="card mt-8 space-y-8 p-16">
            @csrf
            @foreach (\App\Support\OpeningHours::parse($seller->opening_hours) as $day => $hours)
                <div class="flex flex-wrap items-center gap-8 border-b border-sokoni-outline pb-8 last:border-0 last:pb-0" x-data="{ closed: {{ $hours ? 'false' : 'true' }} }">
                    <span class="w-96 text-sm font-medium">{{ __('site.day_'.$day) }}</span>
                    <label class="flex items-center gap-4 text-xs text-sokoni-black/60">
                        <input type="checkbox" name="hours[{{ $day }}][closed]" value="1" x-model="closed" class="accent-sokoni-black">
                        {{ __('site.shop_hours_closed_toggle') }}
                    </label>
                    <input type="time" name="hours[{{ $day }}][open]" value="{{ $hours['open'] ?? '08:00' }}" :disabled="closed" class="input-field w-112" :class="closed ? 'opacity-40' : ''">
                    <span class="text-xs text-sokoni-black/40">&ndash;</span>
                    <input type="time" name="hours[{{ $day }}][close]" value="{{ $hours['close'] ?? '18:00' }}" :disabled="closed" class="input-field w-112" :class="closed ? 'opacity-40' : ''">
                    @error("hours.{$day}.open") <p class="w-full text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                    @error("hours.{$day}.close") <p class="w-full text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <button type="submit" class="btn-primary mt-8 text-sm">{{ __('site.shop_hours_save') }}</button>
        </form>
    </div>

    <h2 class="mt-32 font-semibold">{{ __('site.shop_dashboard_recent_orders') }}</h2>
    @forelse ($recentOrders as $order)
        <div class="card mt-8 flex items-center justify-between p-12">
            <div>
                <p class="text-sm font-medium">{{ $order->code }}</p>
                <p class="text-xs text-sokoni-black/50">{{ $order->buyer->name }}</p>
            </div>
            <span class="chip text-xs">{{ $order->statusLabel() }}</span>
        </div>
    @empty
        <p class="mt-8 text-sm text-sokoni-black/50">{{ __('site.shop_dashboard_no_orders') }}</p>
    @endforelse

    <a href="{{ route('web.account.shop.products') }}" class="btn-secondary mt-24 inline-flex text-sm">{{ __('site.shop_dashboard_manage_products') }}</a>
</div>
@endsection

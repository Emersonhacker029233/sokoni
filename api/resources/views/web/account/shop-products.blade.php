@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">My products</h1>
        <a href="{{ route('web.account.shop.products.create') }}" class="btn-primary text-sm">+ Add product</a>
    </div>

    {{-- Status confirmation now renders globally via partials.flash. --}}

    <div class="mt-16 space-y-8">
        @forelse ($products as $product)
            <a href="{{ route('web.account.shop.products.edit', $product) }}" class="card flex items-center gap-12 p-12">
                <div class="h-48 w-48 shrink-0 overflow-hidden rounded-chip bg-sokoni-surface-alt">
                    @if ($product->media->first())
                        <img src="{{ $product->media->first()->thumb_path }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ $product->title }}</p>
                    <p class="text-sm text-sokoni-black/50">{{ \App\Support\Money::format($product->price) }} &middot; {{ $product->stock }} in stock</p>
                </div>
                <span class="chip text-xs">{{ $product->is_hidden ? 'Hidden' : ($product->is_active ? 'Live' : 'Inactive') }}</span>
            </a>
        @empty
            <p class="text-sm text-sokoni-black/50">No products yet.</p>
        @endforelse
    </div>

    {{ $products->links() }}
</div>
@endsection

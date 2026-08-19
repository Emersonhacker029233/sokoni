@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.account_saved') }}</h1>

    @if ($products->isEmpty())
        <p class="mt-16 text-sm text-sokoni-black/50">Nothing saved yet.</p>
    @else
        <div class="mt-16 grid grid-cols-2 gap-16 sm:grid-cols-3 md:grid-cols-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        {{ $products->links() }}
    @endif
</div>
@endsection

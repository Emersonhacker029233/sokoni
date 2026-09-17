@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">{{ $order->code }}</h1>
        <span class="chip">{{ $order->statusLabel() }}</span>
    </div>
    <p class="text-sm text-sokoni-black/50">{{ $order->seller->shop_name }} &middot; {{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>

    {{-- Status timeline --}}
    <div class="card mt-24 p-16">
        <h2 class="text-sm font-semibold">{{ __('site.order_status_heading') }}</h2>
        <ol class="mt-12 space-y-8">
            @foreach ($timeline as $step)
                <li class="flex items-center gap-8 text-sm">
                    <span class="h-8 w-8 rounded-full bg-sokoni-yellow"></span>
                    <span class="font-medium">{{ \App\Models\Order::labelForStatus($step['status']) }}</span>
                    <span class="text-sokoni-black/40">{{ $step['at']->translatedFormat('d M, H:i') }}</span>
                </li>
            @endforeach
        </ol>
        @if ($order->status === 'cancelled' && $order->cancelled_reason)
            <p class="mt-8 text-sm text-sokoni-danger">{{ __('site.order_reason_prefix', ['reason' => $order->cancelled_reason]) }}</p>
        @endif
    </div>

    {{-- Items --}}
    <div class="card mt-16 p-16">
        <h2 class="text-sm font-semibold">{{ __('site.order_items_heading') }}</h2>
        <div class="mt-12 space-y-8">
            @foreach ($order->items as $item)
                <div class="flex justify-between text-sm">
                    <span>{{ $item->title_snapshot }} &times; {{ $item->qty }}</span>
                    <span>{{ \App\Support\Money::format($item->lineTotal()) }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-12 flex justify-between border-t border-sokoni-outline pt-12 font-semibold">
            <span>{{ __('site.order_total') }}</span>
            <span>{{ \App\Support\Money::format($order->total) }}</span>
        </div>
    </div>

    <div class="card mt-16 p-16 text-sm">
        <h2 class="font-semibold">{{ __('site.order_delivery_heading') }}</h2>
        <p class="mt-8 text-sokoni-black/70">{{ $order->deliveryMethodLabel() }}</p>
        @if ($order->address)
            <p class="text-sokoni-black/70">{{ $order->address }}</p>
        @endif
        @if ($order->notes)
            <p class="mt-8 text-sokoni-black/50">{{ __('site.order_notes_prefix', ['notes' => $order->notes]) }}</p>
        @endif
    </div>

    @if ($order->review)
        <div class="card mt-16 p-16 text-sm">
            <h2 class="font-semibold">{{ __('site.order_your_review') }}</h2>
            <x-star-rating :rating="(float) $order->review->rating" size="14" />
            @if ($order->review->comment)
                <p class="mt-8 text-sokoni-black/70">{{ $order->review->comment }}</p>
            @endif
        </div>
    @elseif ($order->isCompleted())
        {{-- The website had no way to leave a review at all — only the app did
             (tester feedback A3). Reuses the exact same order-completed +
             not-already-reviewed rule the API enforces (StoreReviewRequest),
             so a review left here is validated identically either way. --}}
        <div class="card mt-16 p-16 text-sm" x-data="{ rating: 0 }">
            <h2 class="font-semibold">{{ __('site.order_leave_review') }}</h2>
            <form action="{{ route('web.account.orders.review', $order) }}" method="post" class="mt-12 space-y-12">
                @csrf
                <input type="hidden" name="rating" :value="rating">
                <div class="flex items-center gap-4">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" @click="rating = {{ $i }}" aria-label="{{ $i }} {{ __('site.order_your_review') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" :fill="rating >= {{ $i }} ? '#FAC902' : '#E6E6E1'" class="h-24 w-24">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.538 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.783.57-1.838-.196-1.538-1.118l1.287-3.957a1 1 0 00-.363-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.287-3.958z" />
                            </svg>
                        </button>
                    @endfor
                </div>
                @error('rating') <p class="text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                @error('order') <p class="text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                <textarea name="comment" rows="3" placeholder="{{ __('site.order_review_placeholder') }}" class="input-field w-full">{{ old('comment') }}</textarea>
                <button type="submit" class="btn-primary text-sm" :disabled="rating === 0" :class="rating === 0 ? 'opacity-50' : ''">{{ __('site.order_submit_review') }}</button>
            </form>
        </div>
    @endif
</div>
@endsection

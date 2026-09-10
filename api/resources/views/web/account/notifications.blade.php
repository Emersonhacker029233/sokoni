@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.notifications_title') }}</h1>

    @forelse ($notifications as $notification)
        <div class="card mt-8 flex items-start justify-between gap-16 p-16 {{ $notification->read_at === null ? 'bg-sokoni-yellow/5' : '' }}">
            <div class="min-w-0">
                <p class="font-medium">{{ $notification->title }}</p>
                <p class="mt-4 text-sm text-sokoni-black/60">{{ $notification->body }}</p>
            </div>
            <span class="shrink-0 text-xs text-sokoni-black/40">{{ $notification->created_at->diffForHumans() }}</span>
        </div>
    @empty
        <p class="mt-16 text-sm text-sokoni-black/50">{{ __('site.notifications_empty') }}</p>
    @endforelse

    {{ $notifications->links() }}
</div>
@endsection

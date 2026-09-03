@extends('layouts.app')

@section('content')
@php
    $otherName = auth('web')->id() === $conversation->buyer_id ? $conversation->seller->shop_name : $conversation->buyer->name;

    // @json() splits its argument on every comma to look for an optional
    // encoding-options/depth argument (Illuminate\View\Compilers\Concerns
    // \CompilesJson::compileJson — a plain explode(), not paren/bracket
    // aware), so a multi-key array literal placed directly inside @json()
    // silently truncates at the first internal comma. Computing the array
    // here first, and passing @json() a single comma-free variable,
    // sidesteps the bug entirely.
    $initialMessages = $conversation->messages->map(fn ($m) => [
        'id' => $m->id,
        'body' => $m->body,
        'attachment' => $m->attachment,
        'mine' => $m->sender_id === Auth::id(),
        'sender_name' => $m->sender->name,
        'created_at' => $m->created_at->toIso8601String(),
    ]);
@endphp

<div class="mx-auto max-w-2xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ $otherName }}</h1>
    @if ($conversation->product)
        <a href="{{ route('web.product', ['product' => $conversation->product->id, 'slug' => \Illuminate\Support\Str::slug($conversation->product->title)]) }}" class="mt-4 inline-block text-sm text-sokoni-black/50 hover:underline">
            Re: {{ $conversation->product->title }}
        </a>
    @endif

    <div
        x-data="sokoniThread({{ $conversation->id }}, {{ Auth::id() }}, '{{ route('web.account.messages.poll', $conversation) }}')"
        x-init="start()"
        class="card mt-16 flex h-96 flex-col overflow-y-auto p-16"
        style="height: 24rem"
    >
        <template x-for="message in messages" :key="message.id">
            <div class="mb-8 flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                <div class="max-w-xs rounded-chip px-12 py-8 text-sm" :class="message.mine ? 'bg-sokoni-yellow text-sokoni-black' : 'bg-sokoni-surface-alt'">
                    <p x-text="message.body"></p>
                    <img x-show="message.attachment" :src="message.attachment" class="mt-4 max-h-48 rounded-chip">
                </div>
            </div>
        </template>
    </div>

    <form action="{{ route('web.account.messages.store', $conversation) }}" method="post" enctype="multipart/form-data" class="mt-12 flex gap-8">
        @csrf
        <input type="text" name="body" placeholder="Type a message..." class="input-field flex-1">
        <label class="btn-secondary cursor-pointer text-sm">
            <input type="file" name="attachment" accept="image/*" class="hidden">
            📎
        </label>
        <button type="submit" class="btn-primary text-sm">Send</button>
    </form>
</div>
@endsection

@push('head')
<script>
    function sokoniThread(conversationId, myId, pollUrl) {
        return {
            messages: @json($initialMessages),
            start() {
                // Same 5s-polling design the app uses (CLAUDE.md feature 5: no WebSocket server on shared hosting).
                setInterval(() => this.poll(), 5000);
            },
            poll() {
                fetch(pollUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => { this.messages = data.messages; });
            },
        };
    }
</script>
@endpush

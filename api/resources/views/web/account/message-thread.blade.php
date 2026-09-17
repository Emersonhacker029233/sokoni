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
            {{ __('site.messages_re_prefix', ['title' => $conversation->product->title]) }}
        </a>
    @endif

    <div
        x-data="sokoniThread({{ $conversation->id }}, {{ Auth::id() }}, '{{ route('web.account.messages.poll', $conversation) }}')"
        x-init="start()"
        @keydown.escape.window="lightboxOpen = false"
        @keydown.arrow-right.window="if (lightboxOpen) lightboxIndex = (lightboxIndex + 1) % images.length"
        @keydown.arrow-left.window="if (lightboxOpen) lightboxIndex = (lightboxIndex - 1 + images.length) % images.length"
        class="card mt-16 flex h-96 flex-col overflow-y-auto p-16"
        style="height: 24rem"
    >
        <template x-for="message in messages" :key="message.id">
            <div class="mb-8 flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                <div class="max-w-xs rounded-chip px-12 py-8 text-sm" :class="message.mine ? 'bg-sokoni-yellow text-sokoni-black' : 'bg-sokoni-surface-alt'">
                    <p x-text="message.body"></p>
                    {{-- Part 4 (client feedback): "tapping a sent image
                         does nothing" / "tapping a document opens or
                         downloads it appropriately." Same isImage()
                         extension check as the app's own
                         ChatMessageAttachmentKind, so a URL classifies
                         identically on both surfaces. --}}
                    <template x-if="message.attachment && isImage(message.attachment)">
                        <img
                            :src="message.attachment"
                            class="mt-4 max-h-48 cursor-zoom-in rounded-chip"
                            @click="lightboxIndex = images.indexOf(message.attachment); lightboxOpen = true"
                        >
                    </template>
                    <template x-if="message.attachment && !isImage(message.attachment)">
                        <a
                            :href="message.attachment"
                            target="_blank"
                            rel="noopener"
                            class="mt-4 flex items-center gap-4 underline"
                            :class="message.mine ? 'text-sokoni-black' : 'text-sokoni-black/80'"
                        >
                            📄 <span x-text="fileName(message.attachment)"></span>
                        </a>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <form action="{{ route('web.account.messages.store', $conversation) }}" method="post" enctype="multipart/form-data" class="mt-12 flex gap-8">
        @csrf
        <input type="text" name="body" placeholder="{{ __('site.messages_type_placeholder') }}" class="input-field flex-1">
        <label class="btn-secondary cursor-pointer text-sm">
            <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx" class="hidden">
            📎
        </label>
        <button type="submit" class="btn-primary text-sm">{{ __('site.messages_send') }}</button>
    </form>

    {{-- Full-screen image viewer — same open/close/swipe-navigation
         pattern as the product photo gallery's own lightbox
         (web/product.blade.php), reused here for consistency rather
         than a second, different implementation. Native pinch-zoom/pan
         on a touch device already works on the plain <img> inside this
         overlay (nothing here disables it); there's no site-wide
         click-to-pan equivalent for desktop anywhere else on this site
         to match, so this mirrors exactly what product.blade.php
         already ships: full-size, object-contain, arrow/keyboard
         navigation between every image in the thread. --}}
    <div
        x-show="lightboxOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-16"
        @click="lightboxOpen = false"
    >
        <button
            type="button"
            class="absolute right-16 top-16 text-2xl text-white"
            @click="lightboxOpen = false"
            aria-label="{{ __('site.a11y_close') }}"
        >✕</button>
        <button
            type="button"
            x-show="images.length > 1"
            class="absolute left-16 text-3xl text-white"
            @click.stop="lightboxIndex = (lightboxIndex - 1 + images.length) % images.length"
            aria-label="{{ __('site.a11y_previous') }}"
        >‹</button>
        <template x-for="(image, index) in images" :key="image">
            <img
                x-show="lightboxIndex === index"
                :src="image"
                class="max-h-full max-w-full object-contain"
                @click.stop
            >
        </template>
        <button
            type="button"
            x-show="images.length > 1"
            class="absolute right-16 text-3xl text-white"
            @click.stop="lightboxIndex = (lightboxIndex + 1) % images.length"
            aria-label="{{ __('site.a11y_next') }}"
        >›</button>
    </div>
</div>
@endsection

@push('head')
<script>
    function sokoniThread(conversationId, myId, pollUrl) {
        return {
            messages: @json($initialMessages),
            // Part 4 (client feedback): lightbox state for the
            // full-screen image viewer below.
            lightboxOpen: false,
            lightboxIndex: 0,
            start() {
                // Same 5s-polling design the app uses (CLAUDE.md feature 5: no WebSocket server on shared hosting).
                setInterval(() => this.poll(), 5000);
            },
            poll() {
                fetch(pollUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => { this.messages = data.messages; });
            },
            // Same extension list as the app's own
            // ChatMessageAttachmentKind.isImageAttachment, so a URL
            // classifies identically on both surfaces — there is no
            // stored type/mime column for `attachment`, just a URL.
            isImage(url) {
                const path = url.split('?')[0].toLowerCase();
                return ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg'].some((ext) => path.endsWith(ext));
            },
            fileName(url) {
                const path = url.split('?')[0];
                return path.substring(path.lastIndexOf('/') + 1) || {{ Illuminate\Support\Js::from(__('site.messages_document_fallback')) }};
            },
            // Every image attachment currently loaded in this thread, in
            // order — "where several images exist in a conversation,
            // allow swiping between them."
            get images() {
                return this.messages.filter((m) => m.attachment && this.isImage(m.attachment)).map((m) => m.attachment);
            },
        };
    }
</script>
@endpush

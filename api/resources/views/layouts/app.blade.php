<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0A">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ trim(($title ?? null) ? $title.' — Sokoni' : __('site.meta_default_title')) }}</title>
    <meta name="description" content="{{ $description ?? __('site.meta_default_description') }}">

    @php($canonical = $canonical ?? url()->current())
    <link rel="canonical" href="{{ $canonical }}">
    @foreach (\App\Http\Middleware\SetWebLocale::SUPPORTED as $hreflangLocale)
        <link rel="alternate" hreflang="{{ $hreflangLocale }}" href="{{ $canonical }}?lang={{ $hreflangLocale }}">
    @endforeach
    {{-- Language audit (client feedback): "Kiswahili as the default
         variant" — x-default is what a visitor sees when none of the
         explicit hreflang entries above match their browser, so it must
         point at the same Kiswahili URL SetWebLocale::DEFAULT actually
         serves, not the bare canonical (which happened to be equivalent
         only back when English was the unparked default). --}}
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}?lang={{ \App\Http\Middleware\SetWebLocale::DEFAULT }}">

    {{-- Open Graph / Twitter — critical in this market: links spread through WhatsApp more than anywhere else. --}}
    <meta property="og:site_name" content="Sokoni">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $title ?? __('site.meta_default_title') }}">
    <meta property="og:description" content="{{ $description ?? __('site.meta_og_description_short') }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('images/brand/sokoni_logo.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'Sokoni' }}">
    <meta name="twitter:description" content="{{ $description ?? __('site.meta_og_description_short') }}">
    <meta name="twitter:image" content="{{ $ogImage ?? asset('images/brand/sokoni_logo.png') }}">

    {{-- B4 (tester feedback): a real favicon set, not one 1080px source
         image reused at every size — favicon.ico was previously a 0-byte
         placeholder file (browsers requesting /favicon.ico directly got an
         empty response). Every size below is flattened onto a solid white
         background, never transparent (see the generator script noted in
         DECISIONS.md) — solid so the mark stays legible on a dark browser
         chrome/tab-switcher background, per the tester's own note. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/brand/apple-touch-icon-180.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    @stack('schema')

    {{-- Language audit (client feedback): resources/js/app.js is a
         plain compiled asset — it can't call the translator itself — so
         every user-facing string it needs is handed to it once here, in
         the page's own locale, rather than hardcoded English in the
         script. Defined as a normal (non-module) inline script, which
         always executes immediately at this point in the document,
         ahead of app.js's own deferred module script further down
         regardless of source order. $sokoniI18n itself is shared from a
         View::composer('layouts.app', ...) in AppServiceProvider,
         matching every other value this layout receives that way
         (unreadMessagesCount, unreadNotificationsCount) — rather than a
         @php block computed inline here. --}}
    <script>
        window.sokoniI18n = @json($sokoniI18n);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body
    class="flex min-h-screen flex-col bg-sokoni-surface text-sokoni-black"
    x-data="messageNotifier({{ (int) ($unreadMessagesCount ?? 0) }}, '{{ route('web.account.messages.unread-count') }}', '{{ route('web.chats') }}', {{ auth('web')->check() ? 'true' : 'false' }})"
>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-sokoni-yellow focus:px-16 focus:py-8">{{ __('site.skip_to_content') }}</a>

    {{-- A4 (tester feedback): a new message arriving while browsing
         elsewhere on the site previously surfaced nowhere at all — this is
         the toast half of that fix, the badge half lives in the header and
         bottom nav (both read from the same $store.messages this sets). --}}
    <div
        x-show="toast"
        x-cloak
        x-transition
        role="status"
        aria-live="polite"
        class="fixed bottom-72 left-1/2 z-50 -translate-x-1/2 rounded-chip bg-sokoni-black px-16 py-10 text-sm text-white shadow-lg lg:bottom-16"
    >
        <a :href="toastHref" class="flex items-center gap-8" x-text="toast"></a>
    </div>

    @include('partials.header')
    @include('partials.flash')

    {{-- pb-56 clears the fixed mobile bottom nav (partials.bottom-nav, h-56); it renders lg:hidden so lg:pb-0 removes the gap where it doesn't exist. --}}
    <main id="main" class="flex-1 pb-56 lg:pb-0">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.bottom-nav')
</body>
</html>

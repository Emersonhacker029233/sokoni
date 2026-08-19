<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0A">

    <title>{{ trim(($title ?? null) ? $title.' — Sokoni' : 'Sokoni — Buy and sell anything, near you') }}</title>
    <meta name="description" content="{{ $description ?? 'Sokoni is Tanzania\'s marketplace for verified sellers — browse products, chat with shops, and order safely, near you.' }}">

    @php($canonical = $canonical ?? url()->current())
    <link rel="canonical" href="{{ $canonical }}">
    @foreach (\App\Http\Middleware\SetWebLocale::SUPPORTED as $hreflangLocale)
        <link rel="alternate" hreflang="{{ $hreflangLocale }}" href="{{ $canonical }}?lang={{ $hreflangLocale }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">

    {{-- Open Graph / Twitter — critical in this market: links spread through WhatsApp more than anywhere else. --}}
    <meta property="og:site_name" content="Sokoni">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $title ?? 'Sokoni — Buy and sell anything, near you' }}">
    <meta property="og:description" content="{{ $description ?? 'Sokoni is Tanzania\'s marketplace for verified sellers.' }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('images/brand/sokoni_logo.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'Sokoni' }}">
    <meta name="twitter:description" content="{{ $description ?? 'Sokoni is Tanzania\'s marketplace for verified sellers.' }}">
    <meta name="twitter:image" content="{{ $ogImage ?? asset('images/brand/sokoni_logo.png') }}">

    <link rel="icon" href="{{ asset('images/brand/sokoni_logo_icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/sokoni_logo_icon.png') }}">

    @stack('schema')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-sokoni-surface text-sokoni-black">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-sokoni-yellow focus:px-16 focus:py-8">Skip to content</a>

    @include('partials.header')

    <main id="main" class="flex-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>

<!DOCTYPE html>
{{--
    Deliberately NOT extending layouts.app: that layout's header partial
    runs a real DB query on every render (the category-counts view
    composer). If the error that landed a visitor here WAS a database
    outage, rendering the "pretty" 500 page through the normal layout
    could itself throw — the one page in this whole site that has to stay
    fully self-contained and DB-free, so it can render no matter what else
    just broke.
--}}
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sokoni</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-sokoni-surface px-16 text-center text-sokoni-black">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-96 w-96 text-sokoni-black/15">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
    </svg>

    <h1 class="text-h2 mt-24">{{ __('site.error_500_title') }}</h1>
    <p class="text-body mt-8 max-w-md">{{ __('site.error_500_body') }}</p>

    <div class="mt-24 flex items-center gap-12">
        <a href="{{ url()->current() }}" class="btn-primary">{{ __('site.error_retry') }}</a>
        <a href="{{ route('web.home') }}" class="btn-secondary">{{ __('site.back_to_home') }}</a>
    </div>
</body>
</html>

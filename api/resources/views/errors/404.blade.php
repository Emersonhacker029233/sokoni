@extends('layouts.app')

@section('content')
<div class="container-sokoni flex flex-col items-center py-64 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-96 w-96 text-sokoni-black/15">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>

    <h1 class="text-h2 mt-24">{{ __('site.error_404_title') }}</h1>
    <p class="text-body mt-8 max-w-md">{{ __('site.error_404_body') }}</p>

    <a href="{{ route('web.home') }}" class="btn-primary mt-24">{{ __('site.back_to_home') }}</a>

    @php($popular = app(\App\Services\Catalog\CategoryCatalogService::class)->withCounts()->take(6))
    @if ($popular->isNotEmpty())
        <div class="mt-40 flex flex-wrap justify-center gap-8">
            @foreach ($popular as $category)
                <a href="{{ route('web.category', app(\App\Services\Catalog\CategoryCatalogService::class)->slug($category)) }}" class="chip">
                    {{ $category->name(app()->getLocale()) }}
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection

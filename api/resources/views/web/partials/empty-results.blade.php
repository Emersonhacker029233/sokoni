@php($suggested = app(\App\Services\Catalog\CategoryCatalogService::class)->withCounts()->take(6))

<div class="rounded-card border border-sokoni-outline py-48 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto h-40 w-40 text-sokoni-black/20"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
    <h2 class="mt-16 text-lg font-semibold">{{ __('site.results_empty_title') }}</h2>
    <p class="mx-auto mt-4 max-w-sm text-sm text-sokoni-black/50">{{ __('site.results_empty_body') }}</p>

    <div class="mt-24 flex flex-wrap justify-center gap-8">
        @foreach ($suggested as $category)
            <x-category-chip :href="route('web.category', app(\App\Services\Catalog\CategoryCatalogService::class)->slug($category))">
                {{ $category->name(app()->getLocale()) }}
            </x-category-chip>
        @endforeach
    </div>
</div>

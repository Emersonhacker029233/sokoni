@props(['position'])

{{--
    Renders at most one banner for the given position — "restrained: one
    wide banner below the hero, one mid-page," not a carousel. Collapses to
    genuinely nothing (not even a wrapping element) when the slot is empty,
    so an inactive/unscheduled slot never leaves a placeholder box or a gap
    behind. Impression counted server-side, once, right here on render —
    the only place in the request lifecycle that's guaranteed to run
    exactly when the banner is actually shown to a visitor.
--}}
@php($banner = \App\Models\Banner::live()->forPosition($position)->first())

@if ($banner)
    @php($banner->recordImpression())
    <div class="container-sokoni py-16">
        <a href="{{ route('web.banners.click', $banner) }}" class="block overflow-hidden rounded-card border border-sokoni-outline">
            <img src="{{ $banner->image_path }}" alt="{{ $banner->title }}" loading="lazy" class="h-auto w-full object-cover">
        </a>
    </div>
@endif

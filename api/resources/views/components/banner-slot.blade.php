@props(['position', 'variant' => 'wide', 'class' => ''])

{{--
    Renders at most one banner for the given position — "restrained: one
    wide banner below the hero, one mid-page," not a carousel. Collapses to
    genuinely nothing (not even a wrapping element) when the slot is empty,
    so an inactive/unscheduled slot never leaves a placeholder box or a gap
    behind. Impression counted server-side, once, right here on render —
    the only place in the request lifecycle that's guaranteed to run
    exactly when the banner is actually shown to a visitor.

    Part B (client feedback): "side" is the new noon.com-pattern narrow
    placement (category_strip_side, near_you_side) — a fixed-width column
    next to existing content rather than a full-width strip. Callers hide
    it on mobile themselves via `class="hidden lg:block"` (passed through
    below) since a narrow side ad has nowhere good to go once the layout
    it sits beside collapses to one column — the slot still collapses to
    nothing on its own if the banner itself is inactive/unscheduled,
    independent of that.
--}}
@php($banner = \App\Models\Banner::live()->forPosition($position)->first())

@if ($banner)
    @php($banner->recordImpression())
    @if ($variant === 'side')
        <a href="{{ route('web.banners.click', $banner) }}" class="{{ $class }} w-[160px] shrink-0 overflow-hidden rounded-card border border-sokoni-outline">
            <img src="{{ $banner->image_path }}" alt="{{ $banner->title }}" loading="lazy" class="h-full w-full object-cover">
        </a>
    @else
        <div class="container-sokoni py-16 {{ $class }}">
            <a href="{{ route('web.banners.click', $banner) }}" class="block overflow-hidden rounded-card border border-sokoni-outline">
                <img src="{{ $banner->image_path }}" alt="{{ $banner->title }}" loading="lazy" class="h-auto w-full object-cover">
            </a>
        </div>
    @endif
@endif

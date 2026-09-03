@props(['icon'])

{{--
    categories.icon stores a Material Symbols name (CategorySeeder's real
    values: devices, checkroom, restaurant, chair, spa, smartphone,
    directions_car, agriculture, handyman, more_horiz) — meant for the
    Flutter app, which has the actual Material Icons font bundled and can
    resolve the name directly. The website has no icon font (deliberately —
    a whole font for ten glyphs is wasteful on a 3G connection), so this
    was printing the raw string as visible text. Inline SVG, one per known
    name, plus a generic fallback for anything unmapped (a future category
    added via the admin panel with an icon name not in this list) — no
    font, no extra request, ~200 bytes each, cached with the rest of the
    page's HTML.
--}}
{{-- Path data lives in App\Support\CategoryIcons, shared with the product placeholder-image generator so both surfaces draw the same glyph. --}}
<svg
    {{ $attributes->merge(['class' => 'h-28 w-28']) }}
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>{!! \App\Support\CategoryIcons::pathFor($icon) !!}</svg>

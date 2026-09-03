<?php

namespace App\Support;

/**
 * The inline-SVG path data behind `<x-category-icon>` (category grid) and
 * `App\Support\ProductPlaceholderImage` (product placeholder media) — one
 * shared source so both surfaces draw the exact same glyph for a given
 * category rather than two independently-drawn approximations. Keys match
 * `categories.icon` (Material Symbols names, `CategorySeeder`'s real
 * values) plus `hardware`, added for the demo catalogue's Construction &
 * Hardware category, which has no equivalent in the original 10.
 */
class CategoryIcons
{
    public const PATHS = [
        'devices' => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/>',
        'checkroom' => '<circle cx="12" cy="4.5" r="1.5"/><path d="M12 6l8 6H4l8-6z"/><path d="M4 19h16"/>',
        'restaurant' => '<path d="M4 9h16l-1.5 9.5a2 2 0 0 1-2 1.7H7.5a2 2 0 0 1-2-1.7L4 9z"/><path d="M8 9V7a4 4 0 0 1 8 0v2"/>',
        'chair' => '<path d="M6 4v9a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4"/><path d="M6 13v7M18 13v7M6 10h12"/>',
        'spa' => '<path d="M12 21c-4-2-7-6-7-11a7 7 0 0 1 14 0c0 5-3 9-7 11z"/><path d="M12 10v11"/>',
        'smartphone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 19h2"/>',
        'directions_car' => '<path d="M3 13l2-5a2 2 0 0 1 2-1h10a2 2 0 0 1 2 1l2 5"/><rect x="2" y="13" width="20" height="5" rx="1.5"/><circle cx="7" cy="18.5" r="1.5"/><circle cx="17" cy="18.5" r="1.5"/>',
        'agriculture' => '<path d="M12 21V9"/><path d="M12 9c0-3.5-3-6-7-6 0 3.5 3 6 7 6z"/><path d="M12 13c0-3.5 3-6 7-6 0 3.5-3 6-7 6z"/>',
        'handyman' => '<circle cx="7" cy="7" r="3"/><path d="M9.5 9.5L19 19"/><path d="M16 16l3-3"/>',
        'more_horiz' => '<circle cx="6" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="18" cy="12" r="1.5"/>',
        'hardware' => '<path d="M3 21l6-6"/><path d="M13.5 3.5a3 3 0 0 0-4.24 4.24l1.42 1.42-6 6a2 2 0 1 0 2.83 2.83l6-6 1.42 1.42a3 3 0 0 0 4.24-4.24z"/>',
        'home_work' => '<path d="M3 21V9l7-5 7 5v12"/><path d="M9 21v-6h4v6"/><path d="M17 21v-8l4 2v6z"/>',
        'child_care' => '<rect x="4" y="4" width="7" height="7" rx="1.2"/><rect x="13" y="4" width="7" height="7" rx="1.2"/><rect x="4" y="13" width="7" height="7" rx="1.2"/><rect x="13" y="13" width="7" height="7" rx="1.2"/>',
    ];

    public const FALLBACK = '<path d="M6 8h12l-1 12a2 2 0 0 1-2 1.8H9a2 2 0 0 1-2-1.8L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>';

    public static function pathFor(?string $icon): string
    {
        return self::PATHS[$icon] ?? self::FALLBACK;
    }
}

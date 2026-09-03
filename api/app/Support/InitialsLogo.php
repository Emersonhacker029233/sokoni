<?php

namespace App\Support;

/**
 * Generates a simple, distinct shop logo from a shop's own initials — the
 * demo catalogue's shop directory previously rendered a bare single letter
 * inline (no seller_profiles.logo set), which read as an empty/unfinished
 * product. A plain SVG, not a raster image: no GD/font-file dependency,
 * a few hundred bytes, renders identically everywhere. Real shops should
 * eventually upload a real logo through the existing seller-logo upload
 * flow (UpdateSellerLogoRequest) — this is demo-content only.
 */
class InitialsLogo
{
    /** Alternates the two brand tones so a grid of 12 shop cards doesn't read as one repeated color. */
    private const PALETTE = [
        ['bg' => '#FAC902', 'fg' => '#0A0A0A'],
        ['bg' => '#0A0A0A', 'fg' => '#FAC902'],
    ];

    public static function svg(string $shopName): string
    {
        $initials = self::initials($shopName);
        $palette = self::PALETTE[crc32($shopName) % count(self::PALETTE)];

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
            <circle cx="100" cy="100" r="100" fill="{$palette['bg']}"/>
            <text x="100" y="100" text-anchor="middle" dominant-baseline="central"
                  font-family="'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
                  font-size="76" font-weight="700" fill="{$palette['fg']}">{$initials}</text>
        </svg>
        SVG;
    }

    /** Up to 2 letters — the shop's first two significant words ("Kariakoo Mobile Center" -> "KM"), one letter if the name is a single word. */
    private static function initials(string $shopName): string
    {
        $words = preg_split('/\s+/', trim($shopName)) ?: [];
        $words = array_values(array_filter($words, fn ($w) => $w !== '' && ! in_array(mb_strtolower($w), ['&', 'and', 'the'], true)));

        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return htmlspecialchars(implode('', $letters) ?: '?', ENT_XML1);
    }
}

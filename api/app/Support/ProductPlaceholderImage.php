<?php

namespace App\Support;

/**
 * A clean, labelled placeholder for demo products this environment has no
 * way to photograph or source real photography for — the category's own
 * icon plus its name on a neutral background, never an unrelated stock
 * photo (a beach on a bag of cement is worse than no picture at all; see
 * DECISIONS.md). One SVG serves every size ProductMedia expects
 * (thumb/card/full) since it's vector, not a raster crop — real product
 * photography should replace every one of these before a real launch.
 */
class ProductPlaceholderImage
{
    public static function svg(string $categoryIcon, string $categoryLabel): string
    {
        $icon = CategoryIcons::pathFor($categoryIcon);
        $label = htmlspecialchars($categoryLabel, ENT_XML1);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">
            <rect width="800" height="800" fill="#F7F7F5"/>
            <rect x="1" y="1" width="798" height="798" fill="none" stroke="#E6E6E1" stroke-width="2"/>
            <g transform="translate(320, 300) scale(6.5)" fill="none" stroke="#8A8A85" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
                {$icon}
            </g>
            <text x="400" y="520" text-anchor="middle" font-family="'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif" font-size="32" font-weight="600" fill="#4A4A48">{$label}</text>
        </svg>
        SVG;
    }
}

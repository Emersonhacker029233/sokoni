<?php

/**
 * Defaults for every value the admin Settings page (CLAUDE.md admin
 * rebuild, Section 6) can override at runtime — see App\Support\Settings,
 * which reads the `settings` table first and falls back to these.
 */
return [
    'search_radius_km' => env('SOKONI_SEARCH_RADIUS_KM', 5),
    'max_media_per_product' => env('SOKONI_MAX_MEDIA_PER_PRODUCT', 8),
    'offer_max_duration_days' => env('SOKONI_OFFER_MAX_DURATION_DAYS', 7),
    'report_auto_hide_threshold' => env('SOKONI_REPORT_AUTO_HIDE_THRESHOLD', 3),
];

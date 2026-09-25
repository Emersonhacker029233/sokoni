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
    'max_sms_blast_size' => env('SOKONI_MAX_SMS_BLAST_SIZE', 500),

    // Part 4 (client feedback): "the search-background rotation is
    // currently fixed at 7 seconds — make it a setting the client
    // controls." Global, not per-position — search_background is the
    // only position that ever rotates a single slot between banners
    // (in_focus shows several posters at once, side-by-side, not one
    // at a time; every other position renders at most one banner with
    // nothing to rotate through in the first place), so there is
    // nothing for a second, position-specific value to actually apply
    // to yet. Seconds, not milliseconds — see Settings::bannerRotationSeconds().
    'banner_rotation_seconds' => env('SOKONI_BANNER_ROTATION_SECONDS', 7),

    // Infra throttle, not an admin-facing policy (see Settings.php) — how
    // many recipients ProcessSmsBlasts sends per cron minute-tick, so a
    // large blast is spread over several minutes rather than risking a
    // request timeout or hammering the SMS provider all at once.
    'sms_blast_batch_size' => env('SOKONI_SMS_BLAST_BATCH_SIZE', 20),

    // Website footer/header links that only exist once a real account is
    // set up — unset (the default) hides the icon/button entirely rather
    // than linking to a generic or non-existent page. See DECISIONS.md.
    'social' => [
        'facebook' => env('SOKONI_SOCIAL_FACEBOOK'),
        'instagram' => env('SOKONI_SOCIAL_INSTAGRAM'),
        'x' => env('SOKONI_SOCIAL_X'),
    ],

    // No 'app_store' key here on purpose — there is no iOS build yet.
    // Add one only once a real App Store listing exists (see DECISIONS.md).
    'app_links' => [
        'google_play' => env('SOKONI_APP_GOOGLE_PLAY'),
    ],

    // Real address on the client's own production domain — configurable so
    // it can change without a deploy, but not hidden-when-unset like the
    // social/app links above, since a support contact is essential UI.
    'support_email' => env('SOKONI_SUPPORT_EMAIL', 'support@sokoni.co.tz'),

    // Hostname the beta deployment is served from — see
    // App\Http\Middleware\NoindexBetaHost. Once the site moves to the main
    // domain this simply never matches again; no manual toggle-off needed.
    'beta_hostname' => env('SOKONI_BETA_HOSTNAME', 'beta.sokoni.co.tz'),

    // Part 2 (client feedback): "shared product links don't open" — the
    // Android app now registers an autoVerify App Links intent-filter for
    // this website's host; assetlinks.json (SeoController::assetlinks())
    // is what proves the app actually owns it, matching this exact
    // package + certificate against Google's Digital Asset Links check.
    // The fingerprint is the release keystore's own SHA-256 certificate
    // digest (colon-separated hex) — from `apksigner verify --print-certs`
    // against a real signed release build; changes only if that keystore
    // is ever rotated (see docs/DEPLOY.md's own keystore-loss warning).
    'android_app' => [
        'package_name' => env('SOKONI_ANDROID_PACKAGE', 'tz.co.sokoni.sokoni'),
        'sha256_cert_fingerprint' => env(
            'SOKONI_ANDROID_SHA256_FINGERPRINT',
            '6C:5D:D0:CA:1E:E6:6A:C3:BC:90:A9:A3:49:D1:42:51:31:87:D4:0A:57:94:74:BF:2E:07:60:67:DD:28:B4:DB'
        ),
    ],
];

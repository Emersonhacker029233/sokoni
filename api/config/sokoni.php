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

    // Delivery dispatch (docs/delivery/PLAN.md). Placeholders agreed with
    // the client 2026-10-29; every one is overridable through the
    // `settings` table. Money values are whole TZS. Read them through
    // App\Support\DeliverySettings, never directly.
    'delivery_base_fee' => env('SOKONI_DELIVERY_BASE_FEE', 1500),
    'delivery_per_km_fee' => env('SOKONI_DELIVERY_PER_KM_FEE', 700),
    'delivery_min_fee' => env('SOKONI_DELIVERY_MIN_FEE', 2000),
    'delivery_max_distance_km' => env('SOKONI_DELIVERY_MAX_DISTANCE_KM', 10),
    'delivery_commission_pct' => env('SOKONI_DELIVERY_COMMISSION_PCT', 15),
    // Straight-line distance is multiplied by this to approximate roads.
    'delivery_road_multiplier' => env('SOKONI_DELIVERY_ROAD_MULTIPLIER', 1.3),
    // Comma-separated dispatch waves in km, widest last.
    'delivery_dispatch_radii_km' => env('SOKONI_DELIVERY_DISPATCH_RADII_KM', '3,5,8'),
    'delivery_dispatch_wave_seconds' => env('SOKONI_DELIVERY_DISPATCH_WAVE_SECONDS', 60),
    // Paid to the driver out of the delivery fee when the buyer cancels
    // after a driver was assigned but before pickup (PLAN.md section 6).
    'delivery_buyer_cancel_fee' => env('SOKONI_DELIVERY_BUYER_CANCEL_FEE', 1000),
    // When the SELLER cancels after assignment: true = the platform pays
    // the driver the same cancellation fee and the buyer is refunded in
    // full; false = the driver gets nothing. Awaiting client decision.
    'delivery_seller_cancel_compensates_driver' => env('SOKONI_DELIVERY_SELLER_CANCEL_COMPENSATES_DRIVER', true),
    'delivery_proof_photo_required' => env('SOKONI_DELIVERY_PROOF_PHOTO_REQUIRED', false),
    // false = the delivery fee must be paid by mobile money before
    // dispatch. true = buyers may instead pay the delivery fee (never
    // item money) to the driver in cash.
    'delivery_fee_cash_enabled' => env('SOKONI_DELIVERY_FEE_CASH_ENABLED', false),
    'delivery_location_retention_hours' => env('SOKONI_DELIVERY_LOCATION_RETENTION_HOURS', 72),
];

<?php

/**
 * Delivery dispatch + Sokoni Driver (docs/delivery/PLAN.md).
 *
 * Everything here is infrastructure. The money and dispatch values the
 * admin can override at runtime (fees, commission, radii, timings) live
 * in config/sokoni.php under `delivery_*` keys instead, so the existing
 * App\Support\Settings DB-override mechanism covers them. See
 * App\Support\DeliverySettings.
 */
return [
    // Master switch. false (the default) = every /api/driver/* and
    // /api/delivery/* route 404s, POST /orders ignores `fulfilment`, and
    // no order ever gets a delivery job. Sokoni 1.0 behaves exactly as it
    // did before this feature existed.
    'enabled' => (bool) env('DELIVERY_ENABLED', false),

    'firebase' => [
        // Service-account JSON for project sokoni-71910. Relative paths
        // resolve against the Laravel base path. NEVER commit this file
        // (storage/app/ is git-ignored). Unset or missing file = pushes
        // are logged instead of sent, and live tracking falls back to
        // polling GET /api/delivery/orders/{order}.
        'credentials' => env('FIREBASE_CREDENTIALS'),
        // e.g. https://sokoni-71910-default-rtdb.europe-west1.firebasedatabase.app
        'database_url' => env('FIREBASE_DATABASE_URL'),
    ],

    // Optional road-distance provider. Unset = straight-line x multiplier.
    'openrouteservice_key' => env('OPENROUTESERVICE_API_KEY'),

    // A driver counts as "online and locatable" for dispatch only if their
    // last heartbeat is newer than this. The app sends one every ~30 s.
    'driver_online_ttl_seconds' => (int) env('DELIVERY_DRIVER_ONLINE_TTL', 120),

    // While on a job, the server keeps at most one breadcrumb per this many
    // seconds (live tracking itself goes through Firebase, not here).
    'location_sample_seconds' => 30,

    // Most drivers offered a job in a single wave.
    'max_offers_per_wave' => 20,

    // Customer-unreachable rule (PLAN.md section 6).
    'unreachable_wait_minutes' => 10,
    'unreachable_min_call_attempts' => 2,

    // Wrong delivery-PIN entries before PIN entry locks and support is alerted.
    'pin_max_attempts' => 5,

    // Driver cancellations within 7 days that flag the driver for admin review.
    'driver_cancel_flag_threshold' => 3,
];

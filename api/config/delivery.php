<?php

/**
 * Delivery by Bolt (docs/delivery/BOLT.md) and returns (docs/delivery/RETURNS.md).
 *
 * Every delivery is carried out by a Bolt driver booked through the Bolt
 * Ride Booker API. The Sokoni Driver app and own-driver dispatch are gone
 * (tag pre-bolt-sokoni-drivers has them).
 *
 * Dark launch: all three switches below default to off. With BOLT_ENABLED
 * off (or for a user outside BOLT_TEST_PHONES, or the App Store review
 * account) the API behaves exactly as it did before delivery existed.
 * See App\Support\DeliveryAccess.
 *
 * Money values the admin can override at runtime live in config/sokoni.php
 * (`delivery_*`), read through App\Support\DeliverySettings.
 */
return [
    // Master switch for Bolt delivery. Replaces the old DELIVERY_ENABLED,
    // which is no longer read.
    'enabled' => (bool) env('BOLT_ENABLED', false),

    // Comma-separated E.164 phones. Non-empty = only these accounts see
    // delivery and returns, and a delivery order needs BOTH the buyer and
    // the shop owner on the list. Empty = everyone (once BOLT_ENABLED is on).
    'test_phones' => env('BOLT_TEST_PHONES', ''),

    // Return requests and shop return policies. Needs BOLT_ENABLED too.
    'returns_enabled' => (bool) env('DELIVERY_RETURNS_ENABLED', false),

    'bolt' => [
        // From ridebooker.bolt.eu > Ride Booker API > Renew. Never commit them.
        'client_id' => env('BOLT_CLIENT_ID'),
        'client_secret' => env('BOLT_CLIENT_SECRET'),
        // sandbox | live
        'env' => env('BOLT_ENV', 'sandbox'),
        // From `php artisan bolt:sandbox create` (sandbox only).
        'sandbox_uuid' => env('BOLT_SANDBOX_UUID'),
        // Bearer token Bolt sends on POST /api/webhooks/bolt once they enable
        // webhooks for the account (live only). Empty = the webhook answers 404.
        'webhook_token' => env('BOLT_WEBHOOK_TOKEN'),
        // Bolt's documented webhook source IPs. Empty = no IP check.
        'webhook_ips' => env('BOLT_WEBHOOK_IPS', '18.197.164.122,18.184.149.227,3.120.3.19'),

        'token_url' => env('BOLT_TOKEN_URL', 'https://oidc.bolt.eu/token'),
        'scope' => 'ridebooker:api',
        // The sandbox base is from Bolt's docs. Check the live base and the
        // cancel path against Bolt's Swagger file before BOLT_ENV=live
        // (docs/delivery/BOLT.md, "Confirm before going live").
        'sandbox_base_url' => env('BOLT_SANDBOX_BASE_URL', 'https://node.bolt.eu/ride-booker-api-sandbox'),
        'live_base_url' => env('BOLT_LIVE_BASE_URL', 'https://node.bolt.eu/ride-booker-api'),
        'paths' => [
            'cancel' => env('BOLT_CANCEL_PATH', 'rides/cancel'),
        ],

        'connect_timeout_seconds' => 5,
        'timeout_seconds' => (int) env('BOLT_TIMEOUT_SECONDS', 15),
        // Tries per request on 429/5xx/dropped connections, with backoff.
        'max_attempts' => 3,
        // Bolt's fair-use rule for ride details: once per 10 seconds per ride.
        'poll_seconds' => 10,
        // A quote or booking that keeps failing (Bolt down, account problem)
        // is retried this many times by the minute cron, 1, 2, 4, 8 ... min
        // apart, then the buyer is told no driver could be booked.
        'max_booking_attempts' => (int) env('BOLT_MAX_BOOKING_ATTEMPTS', 5),
    ],

    'firebase' => [
        // Service-account JSON for project sokoni-71910, used for delivery
        // and return pushes only. Relative paths resolve against the Laravel
        // base path. NEVER commit it. Unset = pushes are logged, not sent;
        // every update is still in the in-app inbox and polled by the app.
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
];

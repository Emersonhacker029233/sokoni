<?php

/**
 * Online collections: the Bolt delivery fee and the return-ride fee only.
 * Goods are paid to the shop directly, never through Sokoni.
 *
 * Credentials are read from .env here only, never committed and never
 * logged (docs/delivery/BOLT.md, "Payments with Dalipay").
 */
return [
    // "dalipay" = Dalipay mobile-money collections.
    // "fake" = sandbox gateway (local and staging only; refused in production).
    // "none" or unset = no online payment at all: "Pay now" isn't offered and
    // admins record fees paid outside the app.
    'provider' => env('PAYMENT_PROVIDER', 'none'),

    // Informational only (WALLET_PLAN.md). Dalipay's mode is DALIPAY_ENV below.
    'env' => env('PAYMENT_ENV', 'sandbox'),

    // Mobile-money networks the sandbox gateway offers at "Pay now", in this
    // order. Dalipay's list is `dalipay.providers` below.
    'channels' => ['mpesa', 'airtel', 'mixx', 'halopesa'],

    // A prompt nobody answers counts as failed after this long, so the
    // buyer can send a new one. A payment that still succeeds later is
    // recorded all the same (DeliveryPaymentService::confirm).
    'attempt_timeout_seconds' => (int) env('PAYMENT_ATTEMPT_TIMEOUT_SECONDS', 120),

    // The tracking screen polls every few seconds; the provider is asked
    // about one payment at most this often from those polls. The minute
    // cron always asks.
    'status_query_interval_seconds' => (int) env('PAYMENT_STATUS_QUERY_INTERVAL_SECONDS', 3),

    'fake' => [
        // The pretend payer approves the prompt this many seconds after it was sent.
        'approve_after_seconds' => (int) env('PAYMENT_FAKE_APPROVE_AFTER_SECONDS', 8),
    ],

    'dalipay' => [
        // test | live. Test keys (gw_pk_test_ / gw_sk_test_) only with "test",
        // production keys only with "live". Test mode is refused when
        // APP_ENV=production, like the sandbox gateway.
        'env' => env('DALIPAY_ENV', 'test'),
        // The host Dalipay gives you, without /api/v1.
        'base_url' => env('DALIPAY_BASE_URL'),
        'public_key' => env('DALIPAY_PUBLIC_KEY'),
        'secret_key' => env('DALIPAY_SECRET_KEY'),
        // The "callback secret" next to the webhook URL in Dalipay's dashboard.
        // Without it every webhook is refused and only the status checks work.
        'webhook_secret' => env('DALIPAY_WEBHOOK_SECRET'),
        'timeout_seconds' => (int) env('DALIPAY_TIMEOUT_SECONDS', 15),
        // A prompt that timed out here is still checked with Dalipay for this
        // long (minute cron), so a late approval with a lost webhook is caught.
        'late_check_minutes' => (int) env('DALIPAY_LATE_CHECK_MINUTES', 60),
        // Sokoni channel => Dalipay `provider`, in the order "Pay now" shows
        // them. Mixx by Yas is the former Tigo Pesa: Dalipay's "Tigo".
        'providers' => [
            'mpesa' => 'Mpesa',
            'airtel' => 'Airtel',
            'mixx' => 'Tigo',
            'halopesa' => 'Halopesa',
            'azampesa' => 'Azampesa',
        ],
    ],
];

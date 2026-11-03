<?php

/**
 * Online collections (docs/delivery/WALLET_PLAN.md section 6). Credentials
 * for a real provider will be read from .env here only, never committed.
 */
return [
    // "fake" = sandbox gateway (local and staging only; refused in
    // production). "none" or unset = no online payment at all. The real
    // provider (Dalipay) is added in Part B.
    'provider' => env('PAYMENT_PROVIDER', 'none'),

    // sandbox | live. Informational until a real provider exists.
    'env' => env('PAYMENT_ENV', 'sandbox'),

    // Mobile-money networks the buyer can pick at "Pay now", in this order.
    'channels' => ['mpesa', 'airtel', 'mixx', 'halopesa'],

    // A prompt nobody answers counts as failed after this long, so the
    // buyer can send a new one.
    'attempt_timeout_seconds' => (int) env('PAYMENT_ATTEMPT_TIMEOUT_SECONDS', 120),

    // The tracking screen polls every few seconds; the provider is asked
    // about one payment at most this often from those polls. The minute
    // cron always asks.
    'status_query_interval_seconds' => (int) env('PAYMENT_STATUS_QUERY_INTERVAL_SECONDS', 3),

    'fake' => [
        // The pretend payer approves the prompt this many seconds after it was sent.
        'approve_after_seconds' => (int) env('PAYMENT_FAKE_APPROVE_AFTER_SECONDS', 8),
    ],
];

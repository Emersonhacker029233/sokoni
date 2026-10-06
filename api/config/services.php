<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Social sign-in — see BLOCKERS.md. HttpSocialAuthVerifier fails closed
    // (InvalidSocialTokenException) until these are set.
    //
    // client_secret/redirect are additional to what the mobile app needed:
    // the app verifies an ID token from Google's native SDK (client_id
    // only, no secret required for that). The website's sign-in button
    // uses a real browser OAuth redirect (Laravel Socialite) instead,
    // which needs the full three — see Web\Auth\GoogleAuthController,
    // which hides the button entirely when any is missing rather than
    // sending a visitor into a broken redirect.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // `client_id` (the Service ID) is all `HttpSocialAuthVerifier::verifyApple()`
    // needs to verify the identity token the native SDK already obtained —
    // checked against Apple's own public JWKS, no private key involved.
    // `team_id`/`key_id`/`private_key_path` aren't used by that check at
    // all; they exist only for a server-to-server call to Apple itself
    // (building the ES256 client-secret JWT Apple's own token/revoke
    // endpoints require) — currently unused in this codebase. Deliberately
    // not wired into account deletion yet: doing that properly needs the
    // native SDK's one-time `authorizationCode` captured and exchanged for
    // a refresh token *at sign-in*, which nothing here currently captures
    // or stores — a real feature of its own, not a one-line addition once
    // these three values exist. Logged as a known gap, not an oversight —
    // see BLOCKERS.md.
    'apple' => [
        'client_id' => env('APPLE_SERVICE_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
    ],

    // App Review bypass (Apple rejection PART B — the reviewer has no
    // Tanzanian phone number to receive a real OTP on). Both must be set
    // for the bypass to exist at all — see PhoneOtpService::isReviewAccount().
    // Never set these in a real user's env; this is for a single
    // designated App Store Connect demo account only.
    'review_account' => [
        'phone' => env('REVIEW_ACCOUNT_PHONE'),
        'code' => env('REVIEW_ACCOUNT_CODE'),
    ],

    // Phone OTP delivery — see docs/SMS.md. AppServiceProvider picks the
    // SmsGateway binding from `sms_driver` below, not from which keys
    // happen to be set — an unset/unrecognised driver always falls back
    // to LogSmsGateway (local dev, CI). One of: kibonet, textify, africastalking, beem.
    'sms_driver' => env('SMS_DRIVER'),

    // Kibonet (https://sms.kibonet.co.tz) — Sokoni's active SMS provider
    // (`SMS_DRIVER=kibonet`), swapped in from Textify 2026-10. `number_format`
    // is a config value, not a hardcoded conversion, because Kibonet's docs
    // never state which shape they expect — see docs/SMS.md and
    // KibonetSmsGateway's docblock. `delivery_report_url` is left blank by
    // default; KibonetSmsGateway omits the field entirely rather than send a
    // dead callback URL when it's unset.
    'kibonet' => [
        'api_key' => env('KIBONET_API_KEY'),
        'api_secret' => env('KIBONET_API_SECRET'),
        'sender_id' => env('KIBONET_SENDER_ID', 'SOKONI'),
        'endpoint' => env('KIBONET_ENDPOINT', 'https://sms.kibonet.co.tz/api/v1/vendor/message/send'),
        'number_format' => env('KIBONET_NUMBER_FORMAT', '255'),
        'delivery_report_url' => env('KIBONET_DELIVERY_REPORT_URL'),
    ],

    // Kept as a selectable alternative (`SMS_DRIVER=textify`) — the
    // previously-active provider, see docs/SMS.md.
    'textify' => [
        'api_key' => env('TEXTIFY_API_KEY'),
        'sender_name' => env('TEXTIFY_SENDER_NAME', 'Textify'),
        'endpoint' => env('TEXTIFY_ENDPOINT', 'https://portal.textify.africa/api/v1/messages'),
    ],

    // Kept as a selectable alternative (`SMS_DRIVER=africastalking`) — an
    // earlier previously-active provider (client account "skyfar"), see docs/SMS.md.
    'africastalking' => [
        'username' => env('AT_USERNAME'),
        'api_key' => env('AT_API_KEY'),
        'sender_id' => env('AT_SENDER_ID'),
        'sandbox' => env('AT_SANDBOX', false),
    ],

    // Kept as a selectable alternative (`SMS_DRIVER=beem`) — not the
    // client's actual provider, see docs/SMS.md.
    'beem' => [
        'api_key' => env('BEEM_SMS_API_KEY'),
        'secret_key' => env('BEEM_SMS_SECRET_KEY'),
        'sender_id' => env('BEEM_SMS_SENDER_ID', 'INFO'),
    ],

];

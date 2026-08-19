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

    'apple' => [
        'client_id' => env('APPLE_SERVICE_ID'),
    ],

    // Phone OTP delivery — see docs/SMS.md. BeemSmsGateway is only bound
    // (AppServiceProvider) when both keys below are set; otherwise the
    // container falls back to LogSmsGateway.
    'beem' => [
        'api_key' => env('BEEM_SMS_API_KEY'),
        'secret_key' => env('BEEM_SMS_SECRET_KEY'),
        'sender_id' => env('BEEM_SMS_SENDER_ID', 'INFO'),
    ],

];

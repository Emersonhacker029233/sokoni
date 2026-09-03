<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * Send a one-time password to $phone (E.164).
     *
     * $locale picks the message text ('en' or 'sw') — callers that have no
     * reliable locale for the recipient (e.g. the API, which has no
     * locale-detection middleware the way the website's `SetWebLocale`
     * does) should just leave it at the default rather than guessing.
     */
    public function sendOtp(string $phone, string $code, string $locale = 'en'): void;

    /**
     * Send an arbitrary message body to $phone (E.164) — C6: the admin
     * bulk-SMS tool composes its own text rather than picking from a fixed
     * OTP/notification shape, so this exists as the one generic send path
     * every driver must support alongside sendOtp().
     */
    public function sendMessage(string $phone, string $body): void;
}

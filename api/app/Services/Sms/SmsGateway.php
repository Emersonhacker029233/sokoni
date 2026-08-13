<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /** Send a one-time password to $phone (E.164). */
    public function sendOtp(string $phone, string $code): void;
}

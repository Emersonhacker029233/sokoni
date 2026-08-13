<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * MOCK: no SMS provider is configured yet (see BLOCKERS.md). Logs the OTP
 * instead of sending it, so phone auth is fully testable end to end
 * locally. Swap the `SmsGateway` binding in AppServiceProvider for a real
 * provider (e.g. Beem Africa, Africa's Talking) when credentials land.
 */
class LogSmsGateway implements SmsGateway
{
    public function sendOtp(string $phone, string $code): void
    {
        Log::info("[MOCK SMS] OTP for {$phone}: {$code}");
    }
}

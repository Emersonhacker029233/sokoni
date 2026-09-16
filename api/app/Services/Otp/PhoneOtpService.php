<?php

namespace App\Services\Otp;

use App\Services\Sms\SmsGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Generates, sends (via SmsGateway) and verifies phone-number OTP codes. */
class PhoneOtpService
{
    // Part 3 (client feedback): "show when the current code expires, so
    // the user understands why it stopped working" — public so both the
    // web controller and the API controller can report the same real
    // expiry back to their client, instead of each guessing/duplicating
    // this number independently.
    public const TTL_SECONDS = 300; // 5 minutes

    public function __construct(private readonly SmsGateway $sms) {}

    /** Returns the instant this code expires, so callers can tell the user exactly when/why it stops working. */
    public function requestCode(string $phone, string $locale = 'en'): Carbon
    {
        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($phone), $code, self::TTL_SECONDS);
        $this->sms->sendOtp($phone, $code, $locale);

        return now()->addSeconds(self::TTL_SECONDS);
    }

    public function verifyCode(string $phone, string $code): bool
    {
        $stored = Cache::get($this->cacheKey($phone));
        if ($stored === null || ! Str::is($stored, $code)) {
            return false;
        }

        Cache::forget($this->cacheKey($phone));

        return true;
    }

    private function cacheKey(string $phone): string
    {
        return "otp:{$phone}";
    }
}

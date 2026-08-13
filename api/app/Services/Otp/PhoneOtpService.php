<?php

namespace App\Services\Otp;

use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Generates, sends (via SmsGateway) and verifies phone-number OTP codes. */
class PhoneOtpService
{
    private const TTL_SECONDS = 300; // 5 minutes

    public function __construct(private readonly SmsGateway $sms) {}

    public function requestCode(string $phone): void
    {
        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($phone), $code, self::TTL_SECONDS);
        $this->sms->sendOtp($phone, $code);
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

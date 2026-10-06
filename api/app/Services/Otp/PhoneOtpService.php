<?php

namespace App\Services\Otp;

use App\Services\Sms\SmsGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

    /**
     * Returns the instant this code expires, so callers can tell the user
     * exactly when/why it stops working.
     *
     * App Review bypass (Apple rejection, PART B: "no Tanzanian phone
     * number, cannot receive the SMS"): when `$phone` exactly matches
     * `REVIEW_ACCOUNT_PHONE`, no SMS is sent and no random code is
     * generated at all — `verifyCode()` below accepts only the fixed
     * `REVIEW_ACCOUNT_CODE` for that one number. Both env values are
     * required for the bypass to exist; either blank means this branch is
     * never taken, so nothing changes for a production `.env` that never
     * sets them. The 15-minute/3-request rate limiter on
     * `/auth/otp/request` (AppServiceProvider) still applies identically —
     * nothing here runs above or around it.
     */
    public function requestCode(string $phone, string $locale = 'en'): Carbon
    {
        if ($this->isReviewAccount($phone)) {
            Log::channel('sms')->info('Review-account OTP bypass: request received, no SMS sent', [
                'phone' => $phone,
            ]);

            return now()->addSeconds(self::TTL_SECONDS);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($phone), $code, self::TTL_SECONDS);
        $this->sms->sendOtp($phone, $code, $locale);

        return now()->addSeconds(self::TTL_SECONDS);
    }

    public function verifyCode(string $phone, string $code): bool
    {
        if ($this->isReviewAccount($phone)) {
            $matches = Str::is((string) config('services.review_account.code'), $code);

            Log::channel('sms')->info('Review-account OTP bypass: verify attempt', [
                'phone' => $phone,
                'matched' => $matches,
            ]);

            return $matches;
        }

        $stored = Cache::get($this->cacheKey($phone));
        if ($stored === null || ! Str::is($stored, $code)) {
            return false;
        }

        Cache::forget($this->cacheKey($phone));

        return true;
    }

    /**
     * Exact match only (not `Str::is`'s wildcard semantics, which
     * `verifyCode` above uses for comparing *codes* but would be the
     * wrong tool for a phone number) — and both the configured phone and
     * code must be non-empty, so an unset/partially-set pair simply never
     * matches anything rather than matching every phone with a blank code.
     */
    private function isReviewAccount(string $phone): bool
    {
        $reviewPhone = config('services.review_account.phone');
        $reviewCode = config('services.review_account.code');

        if (! is_string($reviewPhone) || $reviewPhone === '' || ! is_string($reviewCode) || $reviewCode === '') {
            return false;
        }

        return $phone === $reviewPhone;
    }

    private function cacheKey(string $phone): string
    {
        return "otp:{$phone}";
    }
}

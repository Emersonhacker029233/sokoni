<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * App Review demo account on the *password* sign-in route (Apple rejection,
 * Guideline 2.1, build 1.0 (7): the reviewer typed the App Store Connect
 * credentials into the first screen — username/password — and got the
 * generic failure, because only the OTP route knew about the review account).
 *
 * Exists only when both `REVIEW_ACCOUNT_PHONE` and `REVIEW_ACCOUNT_PASSWORD`
 * are set — either blank means `resolve()` never matches anything, so a
 * production `.env` that never sets them behaves exactly as before. The OTP
 * bypass (`REVIEW_ACCOUNT_CODE`, PhoneOtpService) is independent of this and
 * keeps working as an alternative.
 */
class ReviewAccount
{
    /**
     * The review user if `$login` names it — its phone in any common
     * format a reviewer might type (`+255…`, `255…`, `0…`, spaces/dashes),
     * or that user's own username. Only ever the review account: every
     * other login string returns null and takes the normal path untouched.
     */
    public function resolve(string $login): ?User
    {
        $phone = $this->configuredPhone();
        if ($phone === null || $this->configuredPassword() === null) {
            return null;
        }

        $user = User::query()->where('phone', $phone)->where('is_admin', false)->first();
        if ($user === null) {
            return null;
        }

        $matchesPhone = $this->localDigits($login) !== null && $this->localDigits($login) === $this->localDigits($phone);
        $matchesUsername = $user->username !== null && mb_strtolower(trim($login)) === $user->username;

        return ($matchesPhone || $matchesUsername) ? $user : null;
    }

    /** The configured review password, or the account's own stored one (e.g. set via tinker) — either is accepted. */
    public function passwordMatches(User $user, string $password): bool
    {
        $configured = $this->configuredPassword();
        $matches = ($configured !== null && hash_equals($configured, $password))
            || ($user->password !== null && Hash::check($password, $user->password));

        Log::channel('sms')->info('Review-account password sign-in attempt', [
            'user_id' => $user->id,
            'matched' => $matches,
        ]);

        return $matches;
    }

    /** True for the configured review phone — used so the app never shows this account the credential-setup prompt. */
    public function isReviewPhone(?string $phone): bool
    {
        return $phone !== null
            && $this->configuredPassword() !== null
            && $this->configuredPhone() === $phone;
    }

    private function configuredPhone(): ?string
    {
        $phone = config('services.review_account.phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    private function configuredPassword(): ?string
    {
        $password = config('services.review_account.password');

        return is_string($password) && $password !== '' ? $password : null;
    }

    /** `+255 783 029 233`, `255783029233` and `0783029233` all → `783029233`; anything that isn't a Tanzanian mobile number → null. */
    private function localDigits(string $value): ?string
    {
        $stripped = preg_replace('/[\s\-().]/', '', $value) ?? '';

        return preg_match('/^(?:\+?255|0)?([67]\d{8})$/', $stripped, $m) === 1 ? $m[1] : null;
    }
}

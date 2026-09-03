<?php

namespace App\Services\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * C6: turns an admin's audience choice into the actual `users` query the
 * bulk-SMS tool sends to — one place both the live preview count and the
 * blast-creation snapshot go through, so they can never disagree.
 */
class SmsAudienceResolver
{
    public const AUDIENCES = ['verified_sellers', 'buyers', 'all'];

    /**
     * @return Builder<User>
     */
    public static function query(string $audience, bool $consentOnly = false): Builder
    {
        $query = match ($audience) {
            'verified_sellers' => User::query()->whereHas(
                'sellerProfile',
                fn (Builder $q) => $q->where('status', 'verified')
            ),
            // No dedicated "buyer" flag exists on the account itself — the
            // absence of a seller profile is the one thing the rest of the
            // codebase already treats as ground truth for this distinction
            // (see User::isSeller()), so it's used here too rather than
            // trusting `account_intent`, which is only a signup-time hint.
            'buyers' => User::query()->whereDoesntHave('sellerProfile'),
            'all' => User::query(),
            default => throw ValidationException::withMessages(['audience' => "Unknown audience '{$audience}'."]),
        };

        // A blast is never sent to a banned/suspended account or one with
        // no phone number to receive it at all.
        $query->whereNotNull('phone')->whereNull('banned_at');

        if ($consentOnly) {
            $query->where('marketing_consent', true);
        }

        return $query;
    }
}

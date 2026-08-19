<?php

namespace App\Services\SocialAuth;

use App\Models\User;

/**
 * Matches (or creates) the local account for a verified social identity —
 * extracted from `Api\AuthController::socialLogin` so the website's
 * Google sign-in (a real browser OAuth redirect via Socialite) uses the
 * exact same account-matching rule instead of a second implementation:
 * match by provider+provider_id first, fall back to email, and only ever
 * link an *existing* account to a provider if it didn't already have one.
 */
class SocialUserResolver
{
    /** @return array{user: User, isNewAccount: bool} */
    public function resolve(VerifiedIdentity $identity): array
    {
        $user = User::query()
            ->where('provider', $identity->provider)
            ->where('provider_id', $identity->providerId)
            ->first();

        if (! $user && $identity->email) {
            $user = User::query()->where('email', $identity->email)->first();
        }

        $isNewAccount = $user === null;

        if (! $user) {
            $user = User::query()->create([
                'name' => $identity->name ?? 'Sokoni User',
                'email' => $identity->email,
                'avatar' => $identity->avatar,
                'provider' => $identity->provider,
                'provider_id' => $identity->providerId,
            ]);
        } elseif (! $user->provider) {
            $user->forceFill([
                'provider' => $identity->provider,
                'provider_id' => $identity->providerId,
            ])->save();
        }

        return ['user' => $user, 'isNewAccount' => $isNewAccount];
    }
}

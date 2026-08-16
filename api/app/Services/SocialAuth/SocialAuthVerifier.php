<?php

namespace App\Services\SocialAuth;

use App\Exceptions\InvalidSocialTokenException;

interface SocialAuthVerifier
{
    /**
     * Verify a client-supplied identity token/access token for $provider
     * ('google' | 'apple') server-side against the provider's own
     * endpoint, returning the identity it actually attests to.
     *
     * @throws InvalidSocialTokenException if the token is invalid, expired,
     *   or issued for a different app (audience/app-id mismatch).
     */
    public function verify(string $provider, string $token): VerifiedIdentity;
}

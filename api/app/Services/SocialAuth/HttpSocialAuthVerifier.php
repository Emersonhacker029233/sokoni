<?php

namespace App\Services\SocialAuth;

use App\Exceptions\InvalidSocialTokenException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Verifies mobile-native social sign-in tokens server-side, so a client
 * can never forge another user's identity by just POSTing an arbitrary
 * provider_id. Each provider's client credentials (see .env.example) are
 * blockers — see BLOCKERS.md — so these real HTTP/JWT verification calls
 * will fail closed (InvalidSocialTokenException) until they're supplied,
 * rather than silently trusting unverified input.
 */
class HttpSocialAuthVerifier implements SocialAuthVerifier
{
    public function verify(string $provider, string $token): VerifiedIdentity
    {
        return match ($provider) {
            'google' => $this->verifyGoogle($token),
            'apple' => $this->verifyApple($token),
            default => throw new InvalidSocialTokenException("Unsupported provider: {$provider}"),
        };
    }

    private function verifyGoogle(string $idToken): VerifiedIdentity
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if ($response->failed()) {
            throw new InvalidSocialTokenException('Google token verification failed.');
        }

        $payload = $response->json();
        $expectedClientId = config('services.google.client_id');

        if (! $expectedClientId || $payload['aud'] !== $expectedClientId) {
            throw new InvalidSocialTokenException('Google token audience mismatch.');
        }

        return new VerifiedIdentity(
            provider: 'google',
            providerId: $payload['sub'],
            email: $payload['email'] ?? null,
            name: $payload['name'] ?? null,
            avatar: $payload['picture'] ?? null,
        );
    }

    private function verifyApple(string $identityToken): VerifiedIdentity
    {
        $serviceId = config('services.apple.client_id');
        if (! $serviceId) {
            throw new InvalidSocialTokenException('Apple service ID is not configured.');
        }

        $keysResponse = Http::get('https://appleid.apple.com/auth/keys');
        if ($keysResponse->failed()) {
            throw new InvalidSocialTokenException('Could not fetch Apple public keys.');
        }

        try {
            $keys = JWK::parseKeySet($keysResponse->json());
            $claims = JWT::decode($identityToken, $keys);
        } catch (\Throwable $e) {
            throw new InvalidSocialTokenException('Apple identity token signature invalid: '.$e->getMessage());
        }

        if ($claims->iss !== 'https://appleid.apple.com' || $claims->aud !== $serviceId) {
            throw new InvalidSocialTokenException('Apple token issuer/audience mismatch.');
        }

        return new VerifiedIdentity(
            provider: 'apple',
            providerId: $claims->sub,
            email: $claims->email ?? null,
            name: null, // Apple only sends the name on first authorization, client-side.
            avatar: null,
        );
    }
}

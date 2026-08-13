<?php

namespace App\Services\SocialAuth;

final readonly class VerifiedIdentity
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public ?string $email,
        public ?string $name,
        public ?string $avatar,
    ) {}
}

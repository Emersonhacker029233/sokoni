<?php

namespace App\Services\Nida;

final readonly class NidaVerificationResult
{
    private function __construct(
        public string $status,
        public ?string $message,
    ) {}

    public static function pendingManualReview(): self
    {
        return new self('pending_manual_review', 'Submitted for manual review by an admin.');
    }
}

<?php

namespace App\Services\Payment;

/** The provider's answer to "send the prompt": accepted (pending) or refused straight away (failed). */
final readonly class CollectionResult
{
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $failureReason = null,
    ) {}

    public function accepted(): bool
    {
        return $this->status === 'pending' && $this->providerReference !== null;
    }
}

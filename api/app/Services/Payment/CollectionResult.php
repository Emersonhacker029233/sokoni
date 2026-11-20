<?php

namespace App\Services\Payment;

/**
 * The provider's answer to "send the prompt":
 *  - pending: accepted, with the provider's reference to ask about later;
 *  - failed: refused straight away (bad number, refused request);
 *  - unknown: no answer (timeout, provider error), so the prompt may or may
 *    not have gone out. The attempt stays open until it times out, and a
 *    late webhook can still attach it, so there is never a second prompt
 *    while the first one may be on the buyer's phone.
 */
final readonly class CollectionResult
{
    /** @param  array<string, scalar|null>  $meta  provider details worth keeping (shown to admins) */
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $failureReason = null,
        public array $meta = [],
    ) {}

    public function accepted(): bool
    {
        return $this->status === 'pending' && $this->providerReference !== null;
    }

    public function unknown(): bool
    {
        return $this->status === 'unknown';
    }
}

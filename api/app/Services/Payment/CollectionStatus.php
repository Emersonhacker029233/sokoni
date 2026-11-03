<?php

namespace App\Services\Payment;

use Carbon\CarbonImmutable;

/** pending | succeeded | failed, as reported by the provider. */
final readonly class CollectionStatus
{
    public function __construct(
        public string $status,
        public ?string $failureReason = null,
        public ?CarbonImmutable $paidAt = null,
    ) {}
}

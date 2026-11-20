<?php

namespace App\Services\Payment;

use Carbon\CarbonImmutable;

/** pending | succeeded | failed, as reported by the provider. */
final readonly class CollectionStatus
{
    /** @param  array<string, scalar|null>  $meta  provider details worth keeping (shown to admins) */
    public function __construct(
        public string $status,
        public ?string $failureReason = null,
        public ?CarbonImmutable $paidAt = null,
        /** What the provider says it collected, when it says; checked against the stored amount. */
        public ?int $amount = null,
        public array $meta = [],
    ) {}
}

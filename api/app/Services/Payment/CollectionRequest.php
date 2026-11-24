<?php

namespace App\Services\Payment;

final readonly class CollectionRequest
{
    public function __construct(
        /** Our own unique reference for this attempt (payments.idempotency_key). */
        public string $reference,
        public int $amount,
        public string $currency,
        /** E.164, e.g. +255754123456. */
        public string $msisdn,
        /** The network the buyer picked, for display; may be missing. No gateway needs it. */
        public ?string $channel,
        public string $description,
    ) {}
}

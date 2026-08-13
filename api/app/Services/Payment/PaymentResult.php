<?php

namespace App\Services\Payment;

final readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}
}

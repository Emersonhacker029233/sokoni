<?php

namespace App\Services\Delivery;

use RuntimeException;

/** The drop-off is outside the delivery area (or the shop has no location). */
class DeliveryUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}

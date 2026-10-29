<?php

namespace App\Services\Delivery;

final readonly class DistanceResult
{
    /** @param 'road'|'straight_line' $source */
    public function __construct(
        public float $km,
        public string $source,
    ) {}
}

<?php

namespace App\Services\Delivery;

/** Every money value in whole TZS. driver_earnings + commission === fee, always. */
final readonly class DeliveryQuote
{
    public function __construct(
        public float $distanceKm,
        public string $distanceSource,
        public int $baseFee,
        public int $perKmFee,
        public int $fee,
        public float $commissionPct,
        public int $commission,
        public int $driverEarnings,
    ) {}

    public function toArray(): array
    {
        return [
            'distance_km' => $this->distanceKm,
            'distance_source' => $this->distanceSource,
            'base_fee' => $this->baseFee,
            'per_km_fee' => $this->perKmFee,
            'fee' => $this->fee,
            'currency' => 'TZS',
        ];
    }
}

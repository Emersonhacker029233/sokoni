<?php

namespace App\Services\Delivery\Bolt;

/**
 * One vehicle category from POST /rides/estimations.
 *
 * The fare_id is single use and expires after 5 minutes (Bolt docs), so it
 * lives only as long as the booking call that asked for it. Nothing that
 * is cached or stored (toArray) ever includes it.
 */
final class BoltEstimate
{
    public function __construct(
        public readonly ?string $fareId,
        public readonly string $category,
        public readonly ?int $etaSeconds,
        public readonly ?float $minAmount,
        public readonly ?float $maxAmount,
        public readonly ?string $currency,
    ) {}

    public static function fromArray(mixed $row): ?self
    {
        if (! is_array($row)) {
            return null;
        }

        // Bolt's own example capitalises it ("Fare_id"); accept either.
        $fareId = $row['fare_id'] ?? $row['Fare_id'] ?? null;
        $price = is_array($row['price'] ?? null) ? $row['price'] : [];
        $min = self::number($price['minimum_amount'] ?? null);
        $max = self::number($price['maximum_amount'] ?? null);

        // Only the "10.50-12.50" text: read the bounds from it.
        if (($min === null || $max === null) && is_string($price['amount'] ?? null)
            && preg_match('/^\s*([\d.]+)\s*(?:-\s*([\d.]+))?\s*$/', $price['amount'], $m) === 1) {
            $min ??= (float) $m[1];
            $max ??= isset($m[2]) && $m[2] !== '' ? (float) $m[2] : (float) $m[1];
        }

        $eta = $row['eta'] ?? null;

        return new self(
            is_scalar($fareId) && (string) $fareId !== '' ? (string) $fareId : null,
            (string) ($row['vehicle_category'] ?? $row['category'] ?? 'unknown'),
            is_numeric($eta) ? (int) $eta : null,
            $min,
            $max,
            isset($price['currency_code']) ? strtoupper((string) $price['currency_code']) : null,
        );
    }

    /** Bolt says a null ETA means the category can't take a ride right now. */
    public function isBookable(): bool
    {
        return $this->fareId !== null && $this->etaSeconds !== null && $this->maxAmount !== null;
    }

    /** What the customer pays: the estimate's upper bound, rounded up to a whole shilling. */
    public function customerFee(): int
    {
        return (int) ceil(round((float) $this->maxAmount, 2));
    }

    /** Safe to store or cache: no fare_id. */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'eta_seconds' => $this->etaSeconds,
            'min' => $this->minAmount,
            'max' => $this->maxAmount,
            'currency' => $this->currency,
        ];
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

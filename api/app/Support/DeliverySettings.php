<?php

namespace App\Support;

/**
 * Typed reads for the admin-overridable delivery values. Same mechanism as
 * App\Support\Settings (a `settings` table row wins, else config('sokoni.*')),
 * kept in its own class so the existing Settings page and Settings::all()
 * are untouched until the admin phase deliberately adds these to it.
 */
class DeliverySettings
{
    public static function baseFee(): int
    {
        return (int) Settings::get('delivery_base_fee');
    }

    public static function perKmFee(): int
    {
        return (int) Settings::get('delivery_per_km_fee');
    }

    public static function minFee(): int
    {
        return (int) Settings::get('delivery_min_fee');
    }

    public static function maxDistanceKm(): float
    {
        return (float) Settings::get('delivery_max_distance_km');
    }

    public static function commissionPct(): float
    {
        return (float) Settings::get('delivery_commission_pct');
    }

    public static function roadMultiplier(): float
    {
        return (float) Settings::get('delivery_road_multiplier');
    }

    /** @return list<float> ascending, never empty */
    public static function dispatchRadiiKm(): array
    {
        $radii = collect(explode(',', (string) Settings::get('delivery_dispatch_radii_km')))
            ->map(fn ($r) => (float) trim($r))
            ->filter(fn ($r) => $r > 0)
            ->sort()
            ->values()
            ->all();

        return $radii === [] ? [3.0, 5.0, 8.0] : $radii;
    }

    public static function dispatchWaveSeconds(): int
    {
        return max(10, (int) Settings::get('delivery_dispatch_wave_seconds'));
    }

    public static function buyerCancelFee(): int
    {
        return (int) Settings::get('delivery_buyer_cancel_fee');
    }

    public static function sellerCancelCompensatesDriver(): bool
    {
        return self::bool('delivery_seller_cancel_compensates_driver');
    }

    public static function proofPhotoRequired(): bool
    {
        return self::bool('delivery_proof_photo_required');
    }

    public static function feeCashEnabled(): bool
    {
        return self::bool('delivery_fee_cash_enabled');
    }

    public static function locationRetentionHours(): int
    {
        return max(1, (int) Settings::get('delivery_location_retention_hours'));
    }

    /** Settings rows store strings, so "0"/"false" must read as false. */
    private static function bool(string $key): bool
    {
        return filter_var(Settings::get($key), FILTER_VALIDATE_BOOL);
    }
}

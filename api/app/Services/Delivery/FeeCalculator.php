<?php

namespace App\Services\Delivery;

use App\Support\DeliverySettings;

/**
 * fee = max(min_fee, round_up_to_100(base + per_km x km))
 * commission = round(fee x pct / 100); driver_earnings = fee - commission.
 *
 * Rounding UP to the next TSh 100 keeps prices tidy for cash-minded users
 * and never under-charges; earnings are derived by subtraction so the two
 * parts always add back up to exactly the fee.
 */
class FeeCalculator
{
    public function __construct(private readonly RouteDistance $distance) {}

    /**
     * @throws DeliveryUnavailableException when farther than the max distance
     *                                      (pass $enforceMaxDistance=false to price it anyway)
     */
    public function quote(float $fromLat, float $fromLng, float $toLat, float $toLng, bool $enforceMaxDistance = true): DeliveryQuote
    {
        $distance = $this->distance->between($fromLat, $fromLng, $toLat, $toLng);

        $maxKm = DeliverySettings::maxDistanceKm();
        if ($enforceMaxDistance && $maxKm > 0 && $distance->km > $maxKm) {
            throw new DeliveryUnavailableException(
                'too_far',
                __('delivery.too_far', ['km' => rtrim(rtrim(number_format($maxKm, 1), '0'), '.')]),
            );
        }

        return self::price($distance);
    }

    public static function price(DistanceResult $distance): DeliveryQuote
    {
        $base = DeliverySettings::baseFee();
        $perKm = DeliverySettings::perKmFee();
        // round() first so float noise (e.g. 3300.0000001) cannot bump a fee up a whole TSh 100.
        $raw = round($base + $perKm * $distance->km, 2);
        $fee = max(DeliverySettings::minFee(), (int) (ceil($raw / 100) * 100));

        $pct = DeliverySettings::commissionPct();
        $commission = (int) round($fee * $pct / 100);

        return new DeliveryQuote(
            distanceKm: $distance->km,
            distanceSource: $distance->source,
            baseFee: $base,
            perKmFee: $perKm,
            fee: $fee,
            commissionPct: $pct,
            commission: $commission,
            driverEarnings: $fee - $commission,
        );
    }
}

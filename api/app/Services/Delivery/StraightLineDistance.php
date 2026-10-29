<?php

namespace App\Services\Delivery;

use App\Support\DeliverySettings;

/** Default: great-circle distance x the configured road multiplier (1.3). */
class StraightLineDistance implements RouteDistance
{
    public function between(float $fromLat, float $fromLng, float $toLat, float $toLng): DistanceResult
    {
        $km = Haversine::km($fromLat, $fromLng, $toLat, $toLng) * DeliverySettings::roadMultiplier();

        return new DistanceResult(round($km, 2), 'straight_line');
    }
}

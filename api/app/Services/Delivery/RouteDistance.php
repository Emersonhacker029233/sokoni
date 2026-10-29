<?php

namespace App\Services\Delivery;

/** Travel distance between two points, used for pricing. */
interface RouteDistance
{
    public function between(float $fromLat, float $fromLng, float $toLat, float $toLng): DistanceResult;
}

<?php

namespace App\Services\Delivery;

final class Haversine
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Lat/lng bounding box enclosing a circle of $radiusKm, for a cheap
     * indexed SQL prefilter before the exact Haversine check in PHP.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [minLat, maxLat, minLng, maxLng]
     */
    public static function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $dLat = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $dLng = rad2deg($radiusKm / self::EARTH_RADIUS_KM / max(cos(deg2rad($lat)), 0.01));

        return [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];
    }
}

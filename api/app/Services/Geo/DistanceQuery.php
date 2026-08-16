<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distance-based seller lookup powering the "Near You" discovery feed.
 *
 * On MySQL (production) this delegates to `ST_Distance_Sphere` against the
 * generated+spatially-indexed `shop_location` column (see the
 * seller_profiles migration). On MariaDB — which reports the same `mysql`
 * driver name but has no `shop_location` column, since MariaDB rejects
 * that column's generated-column syntax (see ShopLocationSchema) — it
 * computes `ST_Distance_Sphere` inline from the plain `lat`/`lng` columns
 * instead, no spatial index involved. SQLite (local dev/tests) has no
 * spatial functions at all, so it falls back to a Haversine calculation
 * over plain lat/lng in PHP — correct either way, just less scalable than
 * pushing the whole computation into SQL. Given Sokoni's seller counts
 * (Dar es Salaam scale, not global), that trade-off is fine for now;
 * revisit if the seller table grows into the tens of thousands.
 */
class DistanceQuery
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Verified sellers with a set location, within $radiusKm of
     * ($lat, $lng) if given (null = unbounded "All"), sorted nearest first.
     *
     * @return array<int, float> seller_id => distance_km
     */
    public static function nearbySellerDistances(float $lat, float $lng, ?float $radiusKm): array
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return self::phpDistances($lat, $lng, $radiusKm);
        }

        return DatabaseDialect::isMariaDb()
            ? self::mariaDbDistances($lat, $lng, $radiusKm)
            : self::mysqlDistances($lat, $lng, $radiusKm);
    }

    private static function mysqlDistances(float $lat, float $lng, ?float $radiusKm): array
    {
        $query = DB::table('seller_profiles')
            ->select('id')
            ->selectRaw(
                'ST_Distance_Sphere(shop_location, ST_SRID(POINT(?, ?), 4326)) / 1000 as distance_km',
                [$lng, $lat]
            )
            ->where('status', 'verified')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('distance_km');

        if ($radiusKm !== null) {
            $query->having('distance_km', '<=', $radiusKm);
        }

        return $query->get()->pluck('distance_km', 'id')->map(fn ($d) => (float) $d)->toArray();
    }

    // MariaDB has no `shop_location` column (see ShopLocationSchema), so
    // this builds the same POINT inline from lat/lng on every row instead
    // of reading a precomputed/spatially-indexed column. Confirmed
    // ST_Distance_Sphere itself works the same on this MariaDB server.
    private static function mariaDbDistances(float $lat, float $lng, ?float $radiusKm): array
    {
        $query = DB::table('seller_profiles')
            ->select('id')
            ->selectRaw(
                'ST_Distance_Sphere(POINT(lng, lat), POINT(?, ?)) / 1000 as distance_km',
                [$lng, $lat]
            )
            ->where('status', 'verified')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('distance_km');

        if ($radiusKm !== null) {
            $query->having('distance_km', '<=', $radiusKm);
        }

        return $query->get()->pluck('distance_km', 'id')->map(fn ($d) => (float) $d)->toArray();
    }

    private static function phpDistances(float $lat, float $lng, ?float $radiusKm): array
    {
        $sellers = DB::table('seller_profiles')
            ->select('id', 'lat', 'lng')
            ->where('status', 'verified')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        $distances = [];
        foreach ($sellers as $seller) {
            $distance = self::haversineKm($lat, $lng, (float) $seller->lat, (float) $seller->lng);
            if ($radiusKm === null || $distance <= $radiusKm) {
                $distances[$seller->id] = $distance;
            }
        }

        asort($distances);

        return $distances;
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

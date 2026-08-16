<?php

namespace App\Services\Geo;

/**
 * The DDL for `seller_profiles.shop_location` — a STORED generated POINT
 * column derived from lat/lng, with a SPATIAL INDEX, powering
 * `ST_Distance_Sphere` radius search in ProductController@index.
 *
 * MariaDB rejects this exact syntax (`POINT SRID 4326 GENERATED ALWAYS
 * AS (...) STORED` is MySQL-only — MariaDB's generated-column grammar
 * doesn't accept a SRID-typed spatial column this way), so on MariaDB
 * there's no shop_location column at all; DistanceQuery computes the
 * distance inline from lat/lng instead. Pulled out as a pure function
 * (statements in, given a boolean, rather than executing DB::statement
 * itself) purely so the branch is unit-testable without a live MySQL or
 * MariaDB connection — see DatabaseDialectTest.
 */
class ShopLocationSchema
{
    /**
     * @return string[] SQL statements to run, in order. Empty on MariaDB.
     */
    public static function statements(bool $isMariaDb): array
    {
        if ($isMariaDb) {
            return [];
        }

        return [
            'ALTER TABLE seller_profiles ' .
                'ADD shop_location POINT SRID 4326 ' .
                'GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng, lat), 4326))) STORED NULL',
            'ALTER TABLE seller_profiles ADD SPATIAL INDEX seller_profiles_shop_location_spatial (shop_location)',
        ];
    }
}

<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects whether the current `mysql`-driver connection is actually
 * MariaDB. Laravel/PDO report MariaDB connections under the same `mysql`
 * driver name as real MySQL, but MariaDB rejects some MySQL-only DDL (a
 * SRID-typed generated spatial column, specifically — see
 * ShopLocationSchema) and needs a slightly different ST_Distance_Sphere
 * call shape (see DistanceQuery), so driver name alone isn't enough to
 * branch on.
 */
class DatabaseDialect
{
    private static ?bool $isMariaDb = null;

    public static function isMariaDb(): bool
    {
        if (self::$isMariaDb === null) {
            self::$isMariaDb = Schema::getConnection()->getDriverName() === 'mysql'
                && self::versionStringIsMariaDb((string) (DB::selectOne('select version() as v')->v ?? ''));
        }

        return self::$isMariaDb;
    }

    public static function versionStringIsMariaDb(string $version): bool
    {
        return str_contains(strtolower($version), 'mariadb');
    }

    /** Test-only: clears the memoized detection so a test can force a re-check. */
    public static function resetCache(): void
    {
        self::$isMariaDb = null;
    }
}

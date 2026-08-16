<?php

namespace Tests\Unit\Services\Geo;

use App\Services\Geo\DatabaseDialect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure string-matching coverage for the `select version()` check
 * DatabaseDialect::isMariaDb() uses to tell MariaDB apart from real MySQL
 * — both report the same `mysql` PDO driver name, so this string check is
 * the only signal available.
 */
class DatabaseDialectTest extends TestCase
{
    #[DataProvider('mariaDbVersionStrings')]
    public function test_recognises_mariadb_version_strings(string $version): void
    {
        $this->assertTrue(DatabaseDialect::versionStringIsMariaDb($version));
    }

    public static function mariaDbVersionStrings(): array
    {
        return [
            'plain' => ['11.4.2-MariaDB'],
            'with log suffix' => ['10.6.16-MariaDB-log'],
            'cPanel-style' => ['10.11.6-MariaDB-cll-lve'],
        ];
    }

    #[DataProvider('mysqlVersionStrings')]
    public function test_does_not_flag_real_mysql_version_strings_as_mariadb(string $version): void
    {
        $this->assertFalse(DatabaseDialect::versionStringIsMariaDb($version));
    }

    public static function mysqlVersionStrings(): array
    {
        return [
            'plain' => ['8.0.35'],
            'with build metadata' => ['8.4.3-standard'],
        ];
    }
}

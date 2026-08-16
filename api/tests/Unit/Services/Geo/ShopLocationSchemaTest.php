<?php

namespace Tests\Unit\Services\Geo;

use App\Services\Geo\ShopLocationSchema;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the MariaDB/MySQL split in the seller_profiles
 * migration: `ALTER TABLE seller_profiles ADD shop_location POINT SRID
 * 4326 GENERATED ALWAYS AS (...) STORED` is valid MySQL but invalid
 * MariaDB syntax, even though MariaDB reports the same `mysql` PDO driver
 * name. There's no MariaDB server available in this test environment (the
 * suite runs on SQLite — see phpunit.xml), so this exercises the exact
 * DDL-decision logic the migration delegates to rather than running the
 * migration against a live database: if this returns an empty array, the
 * migration executes zero `DB::statement()` calls for shop_location, which
 * is the whole guarantee being tested.
 */
class ShopLocationSchemaTest extends TestCase
{
    public function test_mariadb_gets_no_generated_column_or_spatial_index_statements(): void
    {
        $this->assertSame([], ShopLocationSchema::statements(isMariaDb: true));
    }

    public function test_mysql_gets_the_generated_column_and_spatial_index_statements(): void
    {
        $statements = ShopLocationSchema::statements(isMariaDb: false);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('ADD shop_location POINT SRID 4326', $statements[0]);
        $this->assertStringContainsString('GENERATED ALWAYS AS', $statements[0]);
        $this->assertStringContainsString('STORED', $statements[0]);
        $this->assertStringContainsString('ADD SPATIAL INDEX seller_profiles_shop_location_spatial', $statements[1]);
    }
}

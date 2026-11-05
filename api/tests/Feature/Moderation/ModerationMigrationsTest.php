<?php

namespace Tests\Feature\Moderation;

use App\Models\AppNotification;
use App\Models\Message;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The five moderation migrations go straight onto the production database,
 * which already holds live data, with no staging run first. On a database
 * with rows in every table they touch, these tests prove that they:
 *
 * - leave existing rows in place, still visible (is_hidden false) and
 *   with every new column null,
 * - roll back and re-apply cleanly, and do nothing when run a second time,
 * - leave nothing half-made when one fails part-way, so a retry completes
 *   them instead of recording them as done without their keys.
 */
class ModerationMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private const FILES = [
        '2026_11_04_100000_create_user_blocks_table',
        '2026_11_04_100100_add_is_hidden_to_moderated_content',
        '2026_11_04_100200_add_actor_id_to_app_notifications_table',
        '2026_11_04_100300_add_moderation_timestamps_to_reports_table',
        '2026_11_04_100400_create_notifications_table',
    ];

    /** @var array<string, Migration> */
    private array $migrations = [];

    private function migration(string $name): Migration
    {
        return $this->migrations[$name] ??= require database_path("migrations/{$name}.php");
    }

    /** @return array<string, int> */
    private function seedLiveData(): array
    {
        $seller = SellerProfile::factory()->verified()->create();
        Message::factory()->count(3)->create();
        Showcase::factory()->create(['seller_id' => $seller->id]);
        Update::factory()->create(['seller_id' => $seller->id]);
        AppNotification::factory()->count(2)->create();
        Report::factory()->count(2)->create();

        return $this->rowCounts();
    }

    /** @return array<string, int> */
    private function rowCounts(): array
    {
        return collect(['users', 'seller_profiles', 'messages', 'showcases', 'updates', 'app_notifications', 'reports'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }

    private function downAll(): void
    {
        foreach (array_reverse(self::FILES) as $name) {
            $this->migration($name)->down();
        }
    }

    private function upAll(): void
    {
        foreach (self::FILES as $name) {
            $this->migration($name)->up();
        }
    }

    private function assertFullyApplied(): void
    {
        $this->assertTrue(Schema::hasTable('user_blocks'));
        $this->assertTrue(Schema::hasTable('notifications'));
        foreach (['seller_profiles', 'messages', 'showcases', 'updates'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'is_hidden'), "{$table}.is_hidden");
        }
        $this->assertTrue(Schema::hasColumn('app_notifications', 'actor_id'));
        $this->assertTrue(Schema::hasColumns('reports', ['alerted_at', 'resolved_at']));

        $this->assertTrue($this->hasIndex('user_blocks', ['blocker_id', 'blocked_id'], unique: true));
        $this->assertTrue($this->hasIndex('user_blocks', ['blocked_id']));
        $this->assertTrue($this->hasIndex('app_notifications', ['actor_id']));
        $this->assertTrue($this->hasIndex('notifications', ['notifiable_type', 'notifiable_id']));
    }

    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['columns'] === $columns && (! $unique || $index['unique']));
    }

    public function test_existing_rows_stay_put_and_stay_visible_through_down_up_and_a_second_up(): void
    {
        $before = $this->seedLiveData();

        $this->downAll();
        $this->assertFalse(Schema::hasTable('user_blocks'));
        $this->assertFalse(Schema::hasTable('notifications'));
        $this->assertFalse(Schema::hasColumn('messages', 'is_hidden'));
        $this->assertFalse(Schema::hasColumn('app_notifications', 'actor_id'));
        $this->assertFalse(Schema::hasColumn('reports', 'alerted_at'));
        $this->assertSame($before, $this->rowCounts(), 'rolling back loses no existing rows');

        // As production meets them: existing rows, then the five in order.
        $this->upAll();
        $this->assertFullyApplied();
        $this->assertSame($before, $this->rowCounts(), 'migrating loses no existing rows');

        foreach (['seller_profiles', 'messages', 'showcases', 'updates'] as $table) {
            $this->assertSame(0, DB::table($table)->where('is_hidden', true)->count(), "every existing {$table} row stays visible");
        }
        $this->assertSame(0, DB::table('app_notifications')->whereNotNull('actor_id')->count());
        $this->assertSame(0, DB::table('reports')->whereNotNull('alerted_at')->orWhereNotNull('resolved_at')->count());

        // Run again (a repeated cron, a manual retry): changes nothing.
        $this->upAll();
        $this->assertFullyApplied();
        $this->assertSame($before, $this->rowCounts());
    }

    public function test_a_create_table_that_fails_part_way_leaves_no_table_and_a_retry_completes_it(): void
    {
        foreach ([
            '2026_11_04_100000_create_user_blocks_table' => ['user_blocks', 'user_blocks_blocked_id_index'],
            '2026_11_04_100400_create_notifications_table' => ['notifications', 'notifications_notifiable_type_notifiable_id_index'],
        ] as $name => [$table, $lastIndex]) {
            $this->migration($name)->down();

            // SQLite index names are database-wide: taking the name of the
            // table's last index makes that statement, the one after
            // CREATE TABLE, fail.
            DB::statement("create index \"{$lastIndex}\" on users (name)");
            try {
                $this->migration($name)->up();
                $this->fail("{$name} should have failed on the taken index name");
            } catch (QueryException) {
                // expected
            }
            $this->assertFalse(Schema::hasTable($table), "{$name} removed its half-made table");

            DB::statement("drop index \"{$lastIndex}\"");
            $this->migration($name)->up();
        }

        $this->assertFullyApplied();
    }

    public function test_the_actor_id_column_is_taken_back_out_when_its_index_fails_and_a_retry_completes_it(): void
    {
        $name = '2026_11_04_100200_add_actor_id_to_app_notifications_table';
        AppNotification::factory()->count(2)->create(['user_id' => User::factory()]);
        $rows = DB::table('app_notifications')->count();

        $this->migration($name)->down();
        DB::statement('create index "app_notifications_actor_id_index" on users (name)');
        try {
            $this->migration($name)->up();
            $this->fail('the migration should have failed on the taken index name');
        } catch (QueryException) {
            // expected
        }
        $this->assertFalse(Schema::hasColumn('app_notifications', 'actor_id'), 'the half-added column was taken back out');
        $this->assertSame($rows, DB::table('app_notifications')->count());

        DB::statement('drop index "app_notifications_actor_id_index"');
        $this->migration($name)->up();

        $this->assertTrue(Schema::hasColumn('app_notifications', 'actor_id'));
        $this->assertTrue($this->hasIndex('app_notifications', ['actor_id']));
        $this->assertSame($rows, DB::table('app_notifications')->count());
    }
}

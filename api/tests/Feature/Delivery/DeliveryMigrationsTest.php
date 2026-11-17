<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The new delivery migrations go onto production (and onto staging, which
 * already holds own-driver delivery rows). They must leave existing rows
 * readable and untouched, roll back and re-apply cleanly, and do nothing
 * when run a second time.
 */
class DeliveryMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private const FILES = [
        '2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table',
    ];

    /** @var array<string, Migration> */
    private array $migrations = [];

    private function migration(string $name): Migration
    {
        return $this->migrations[$name] ??= require database_path("migrations/{$name}.php");
    }

    /** An own-driver job as staging has them, written the way the old code did. */
    private function legacyJob(): int
    {
        $order = Order::factory()->create(['fulfilment' => 'dispatch', 'delivery_method' => 'delivery']);

        return DB::table('delivery_jobs')->insertGetId([
            'order_id' => $order->id, 'status' => 'delivered',
            'pickup_lat' => -6.8, 'pickup_lng' => 39.2, 'dropoff_lat' => -6.7, 'dropoff_lng' => 39.3,
            'distance_km' => 3.3, 'distance_source' => 'straight_line', 'base_fee' => 1500, 'per_km_fee' => 700,
            'fee' => 4500, 'commission_pct' => 15, 'commission' => 675, 'driver_earnings' => 3825,
            'payment_status' => 'paid', 'delivery_pin' => encrypt('1234', false), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_existing_rows_survive_down_up_and_a_second_up_untouched(): void
    {
        $id = $this->legacyJob();
        $before = (array) DB::table('delivery_jobs')->where('id', $id)->first();

        foreach (array_reverse(self::FILES) as $name) {
            $this->migration($name)->down();
        }
        $this->assertFalse(Schema::hasColumn('delivery_jobs', 'provider'));
        $this->assertSame(1, DB::table('delivery_jobs')->count());

        foreach ([1, 2] as $_) {
            foreach (self::FILES as $name) {
                $this->migration($name)->up();
            }
        }

        $this->assertTrue(Schema::hasColumns('delivery_jobs', ['provider', 'provider_ref', 'provider_driver', 'estimate_max', 'final_cost', 'cost_difference', 'booking_attempts', 'pending_payment_at']));
        $after = (array) DB::table('delivery_jobs')->where('id', $id)->first();
        foreach ($before as $column => $value) {
            $this->assertEquals($value, $after[$column], "{$column} changed");
        }

        $job = DeliveryJob::query()->findOrFail($id);
        $this->assertNull($job->provider, 'an own-driver job is not a Bolt job');
        $this->assertSame(0, (int) $job->booking_attempts);
        $this->assertNull($job->provider_driver);
        $this->assertSame('delivered', $job->status);
    }

    public function test_a_part_way_failure_leaves_nothing_behind(): void
    {
        $migration = $this->migration(self::FILES[0]);
        $migration->down();
        // Another table already holds the last index name it wants (SQLite index names are database-wide):
        // it fails after adding the columns and the first index.
        Schema::table('orders', fn ($t) => $t->index(['status'], 'delivery_jobs_provider_ref_index'));

        try {
            $migration->up();
            $this->fail('expected the migration to fail');
        } catch (\Throwable) {
        }

        $this->assertFalse(Schema::hasColumn('delivery_jobs', 'provider'), 'the columns it had added are gone again');
        $this->assertFalse(Schema::hasIndex('delivery_jobs', 'delivery_jobs_provider_status_index'));
    }
}

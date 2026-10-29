<?php

namespace Tests\Feature\Delivery;

use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Waves: 3 km now, 5 km after 60 s, 8 km after 120 s, no_driver_found after 180 s. */
class DispatchTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    /** Online drivers ping every ~30 s in real life; keep them fresh across simulated time jumps. */
    private function heartbeat(): void
    {
        DriverProfile::query()->where('is_online', true)->update(['last_located_at' => now()]);
    }

    private function offeredDriverIds($job): array
    {
        return $job->offers()->pluck('driver_profile_id')->sort()->values()->all();
    }

    public function test_waves_expand_3_then_5_then_8_km_then_give_up(): void
    {
        $d1 = $this->driverAt(1);
        $d4 = $this->driverAt(4);
        $d7 = $this->driverAt(7);
        $d10 = $this->driverAt(10);

        $job = $this->paidJob();
        $this->assertSame([$d1->id], $this->offeredDriverIds($job));

        // Within the wave window nothing changes.
        $this->travel(30)->seconds();
        $this->heartbeat();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();
        $this->assertSame(1, $job->fresh()->dispatch_wave);

        $this->travel(31)->seconds();
        $this->heartbeat();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();
        $this->assertSame(2, $job->fresh()->dispatch_wave);
        $this->assertSame([$d1->id, $d4->id], $this->offeredDriverIds($job));

        $this->travel(61)->seconds();
        $this->heartbeat();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();
        $this->assertSame(3, $job->fresh()->dispatch_wave);
        $this->assertSame([$d1->id, $d4->id, $d7->id], $this->offeredDriverIds($job));

        $this->travel(61)->seconds();
        $this->heartbeat();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $job->refresh();
        $this->assertSame('no_driver_found', $job->status);
        $this->assertNotContains($d10->id, $this->offeredDriverIds($job), '10 km is outside every wave.');
        $this->assertSame(0, $job->offers()->where('status', 'offered')->count());
        $this->assertSame(3, $job->offers()->where('status', 'expired')->count());
        $this->assertTrue($this->pushes->to($job->order->buyer)->contains(fn ($p) => $p['data']['status'] === 'no_driver_found'));
    }

    public function test_driver_polling_advances_waves_without_cron(): void
    {
        $d4 = $this->driverAt(4);
        $job = $this->paidJob();
        $this->assertSame([], $this->offeredDriverIds($job));

        $this->travel(61)->seconds();
        // Keep the polling driver's heartbeat fresh across the time jump.
        $d4->forceFill(['last_located_at' => now()])->save();

        $this->asDriver($d4)->getJson('/api/driver/offers')->assertOk();

        $this->assertSame(2, $job->fresh()->dispatch_wave);
        $this->asDriver($d4)->getJson('/api/driver/offers')
            ->assertOk()
            ->assertJsonPath('data.0.job_id', $job->id);
    }

    public function test_buyer_polling_advances_its_own_job(): void
    {
        $job = $this->paidJob();
        $this->travel(61)->seconds();

        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk();

        $this->assertSame(2, $job->fresh()->dispatch_wave);
    }

    public function test_ineligible_drivers_are_never_offered(): void
    {
        $eligible = $this->driverAt(1);
        $offline = $this->driverAt(1);
        $offline->forceFill(['is_online' => false])->save();
        $stale = $this->driverAt(1);
        $stale->forceFill(['last_located_at' => now()->subMinutes(5)])->save();
        $pending = $this->driverAt(1);
        $pending->forceFill(['status' => 'pending'])->save();
        $suspended = $this->driverAt(1);
        $suspended->forceFill(['status' => 'suspended'])->save();
        $unverifiedVehicle = $this->driverAt(1);
        $unverifiedVehicle->vehicles()->update(['verified_at' => null]);

        $buyerWhoDrives = $this->driverAt(1);

        $job = $this->paidJob($buyerWhoDrives->user);

        $this->assertSame([$eligible->id], $this->offeredDriverIds($job));
    }

    public function test_a_busy_driver_is_not_offered_a_second_job(): void
    {
        $driver = $this->driverAt(1);
        $first = $this->paidJob();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$first->id}/accept")->assertOk();

        $second = $this->paidJob(\App\Models\User::factory()->create());

        $this->assertSame(0, $second->offers()->count());
    }

    public function test_newly_online_drivers_join_the_current_wave_on_the_next_tick(): void
    {
        $job = $this->paidJob();
        $this->assertSame(0, $job->offers()->count());

        $late = $this->driverAt(2);
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $this->assertSame([$late->id], $this->offeredDriverIds($job));
        $this->assertSame(1, $job->fresh()->dispatch_wave);
    }

    public function test_offer_push_goes_to_the_driver_app_only(): void
    {
        $driver = $this->driverAt(1);
        $this->paidJob();

        $offer = $this->pushes->ofType('delivery_offer')->firstWhere('user_id', $driver->user_id);
        $this->assertSame('driver', $offer['app']);
        $this->assertFalse($offer['silent']);
    }

    public function test_tick_is_a_no_op_when_the_feature_is_off(): void
    {
        $this->driverAt(4);
        $job = $this->paidJob();
        config(['delivery.enabled' => false]);

        $this->travel(61)->seconds();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $this->assertSame(1, $job->fresh()->dispatch_wave);
        $this->assertSame(0, DriverProfile::query()->whereHas('offers')->count());
    }
}

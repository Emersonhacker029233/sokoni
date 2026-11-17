<?php

namespace Tests\Feature\Delivery\Bolt;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/** `php artisan bolt:sandbox`: the hand-driven sandbox end to end, and never against a live account. */
class BoltSandboxCommandTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_create_prints_the_uuid_to_put_in_env(): void
    {
        $this->artisan('bolt:sandbox create')
            ->expectsOutputToContain('BOLT_SANDBOX_UUID=')
            ->assertSuccessful();
    }

    public function test_moving_a_ride_by_order_code_syncs_the_delivery_at_once(): void
    {
        $job = $this->bookedJob();

        $this->artisan('bolt:sandbox', ['action' => 'state', 'ride' => $job->order->code, 'state' => 'driver_on_route_to_client'])
            ->expectsOutputToContain('is now: driver_assigned')
            ->assertSuccessful();
        $this->artisan('bolt:sandbox', ['action' => 'state', 'ride' => '1001', 'state' => 'COMPLETED'])
            ->expectsOutputToContain('is now: delivered')
            ->assertSuccessful();

        $this->assertSame('completed', $job->order->fresh()->status);
    }

    public function test_it_refuses_to_touch_a_live_account(): void
    {
        config(['delivery.bolt.env' => 'live']);

        $this->artisan('bolt:sandbox state 1001 COMPLETED')->assertFailed();
        $this->assertSame([], $this->boltRides);
    }

    public function test_bolt_errors_are_shown_with_their_headers(): void
    {
        $this->boltFailNext('state', 400, 'RIDE_BOOKER_API_MISSING_ORDER');

        $this->artisan('bolt:sandbox state 4242 COMPLETED')
            ->expectsOutputToContain('x-bolt-error-code: RIDE_BOOKER_API_MISSING_ORDER')
            ->expectsOutputToContain('x-bolt-tracking: trk-state')
            ->assertFailed();
    }
}

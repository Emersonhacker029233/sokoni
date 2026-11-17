<?php

namespace Tests\Feature\Delivery\Bolt;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/** POST /api/webhooks/bolt: Bolt's own bearer token and source IPs, then the same state handling as polling. */
class BoltWebhookTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    private function hook(array $body, ?string $token = 'hook-secret', string $ip = '18.197.164.122')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders($token === null ? [] : ['Authorization' => "Bearer {$token}"])
            ->postJson('/api/webhooks/bolt', $body);
    }

    public function test_a_valid_webhook_reads_the_ride_back_from_bolt_and_moves_the_delivery(): void
    {
        $job = $this->bookedJob();
        $this->boltState('1001', 'DRIVER_ON_ROUTE_TO_CLIENT');

        $this->hook(['ride_id' => 1001, 'type' => 'ON_DEMAND', 'state' => 'DRIVER_ON_ROUTE_TO_CLIENT'])
            ->assertOk()->assertJson(['received' => true, 'handled' => true]);

        $job->refresh();
        $this->assertSame('driver_assigned', $job->status);
        $this->assertSame('Seyf Driver', $job->provider_driver['name'], 'driver details come from the details call, not the webhook body');
        $this->assertSame(1, $job->events()->where('type', 'provider_webhook')->count());
    }

    public function test_when_bolt_cannot_be_asked_the_webhook_state_is_applied_on_its_own(): void
    {
        $job = $this->bookedJob();
        foreach (range(1, 3) as $_) {
            $this->boltFailNext('details', 503);
        }

        $this->hook(['ride_id' => 1001, 'type' => 'ON_DEMAND', 'state' => 'NO_DRIVER_FOUND'])->assertOk();

        $this->assertSame('no_driver_found', $job->fresh()->status);
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        $job = $this->bookedJob();

        $this->hook(['ride_id' => 1001, 'state' => 'COMPLETED'], 'guess')->assertUnauthorized();
        $this->hook(['ride_id' => 1001, 'state' => 'COMPLETED'], null)->assertUnauthorized();

        $this->assertSame('pending_driver', $job->fresh()->status);
    }

    public function test_only_bolts_documented_ips_may_call_it(): void
    {
        config(['delivery.bolt.webhook_ips' => '18.197.164.122,18.184.149.227,3.120.3.19']);
        $job = $this->bookedJob();

        $this->hook(['ride_id' => 1001, 'state' => 'COMPLETED'], ip: '203.0.113.9')->assertForbidden();
        $this->assertSame('pending_driver', $job->fresh()->status);

        $this->boltState('1001', 'ARRIVED_AT_CLIENT');
        $this->hook(['ride_id' => 1001, 'state' => 'ARRIVED_AT_CLIENT'], ip: '3.120.3.19')->assertOk();
        $this->assertSame('arrived_at_shop', $job->fresh()->status);
    }

    public function test_unknown_rides_and_other_ride_types_are_acknowledged_and_ignored(): void
    {
        $this->hook(['ride_id' => 999999, 'type' => 'ON_DEMAND', 'state' => 'COMPLETED'])->assertOk()->assertJson(['handled' => false]);
        $this->hook(['ride_id' => 1, 'type' => 'SCHEDULED', 'state' => 'SCHEDULED'])->assertOk()->assertJson(['handled' => false]);
    }

    public function test_the_webhook_does_not_exist_while_bolt_is_off_or_has_no_token(): void
    {
        config(['delivery.bolt.webhook_token' => '']);
        $this->hook(['ride_id' => 1001, 'state' => 'COMPLETED'])->assertNotFound();

        config(['delivery.bolt.webhook_token' => 'hook-secret', 'delivery.enabled' => false]);
        $this->hook(['ride_id' => 1001, 'state' => 'COMPLETED'])->assertNotFound();
    }

    public function test_a_malformed_body_is_rejected(): void
    {
        $this->hook(['type' => 'ON_DEMAND'])->assertUnprocessable()->assertJsonValidationErrors(['ride_id', 'state']);
    }
}

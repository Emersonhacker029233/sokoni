<?php

namespace Tests\Feature\Delivery;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A driver or customer only ever sees their own jobs, and phones/location only while active. */
class PermissionsTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_every_delivery_route_404s_when_the_flag_is_off(): void
    {
        $job = $this->paidJob();
        config(['delivery.enabled' => false]);
        $user = $job->order->buyer;

        $this->actingAs($user)->getJson('/api/driver/me')->assertNotFound();
        $this->actingAs($user)->getJson('/api/driver/offers')->assertNotFound();
        $this->actingAs($user)->getJson("/api/delivery/orders/{$job->order_id}")->assertNotFound();
        $this->actingAs($user)->getJson('/api/delivery/quote?seller_id=1&lat=0&lng=0')->assertNotFound();
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/driver/me')->assertUnauthorized();
        $this->getJson('/api/delivery/orders/1')->assertUnauthorized();
    }

    public function test_another_customer_cannot_see_or_act_on_my_delivery(): void
    {
        $job = $this->paidJob();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson("/api/delivery/orders/{$job->order_id}")->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/delivery/orders/{$job->order_id}/retry")->assertForbidden();
        $this->assertSame('pending_driver', $job->fresh()->status);
    }

    public function test_seller_reads_status_but_never_the_pin_or_live_location(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        $seller = $job->order->seller->user;
        $data = $this->actingAs($seller)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk()->json('data');

        $this->assertSame('driver_assigned', $data['status']);
        $this->assertArrayNotHasKey('delivery_pin', $data);
        $this->assertArrayNotHasKey('driver_location', $data);
        $this->assertSame($job->pickupCode(), $data['pickup_code']);
        $this->assertSame($driver->user->name, $data['driver']['name']);

        // And cannot act as the buyer.
        $this->actingAs($seller)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertForbidden();
    }

    public function test_buyer_sees_pin_driver_phone_and_location_only_while_active(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $buyer = $job->order->buyer;
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        $active = $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk()->json('data');
        $this->assertSame($job->fresh()->delivery_pin, $active['delivery_pin']);
        $this->assertSame($driver->user->phone, $active['driver']['phone']);
        $this->assertNotNull($active['driver_location']);
        $this->assertSame('boda_boda', $active['driver']['vehicle']['type']);

        $job = $this->driveToArrived($job, $driver);
        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/status", ['status' => 'delivered', 'pin' => $job->delivery_pin])->assertOk();

        $after = $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk()->json('data');
        $this->assertNull($after['delivery_pin']);
        $this->assertNull($after['driver']['phone']);
        $this->assertNull($after['driver_location']);
        $this->assertNull($after['tracking']);
        $this->assertSame($driver->user->name, $after['driver']['name'], 'Name stays so the buyer can rate.');
    }

    public function test_offer_preview_hides_customer_identity_and_exact_address(): void
    {
        $driver = $this->driverAt(1);
        $this->paidJob();

        $offer = $this->asDriver($driver)->getJson('/api/driver/offers')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('customer', $offer);
        $this->assertSame(round(self::DROP_LAT, 2), $offer['dropoff_area']['lat']);
        $this->assertStringNotContainsString('Uhuru', json_encode($offer));
    }

    public function test_a_driver_cannot_see_or_move_another_drivers_job(): void
    {
        $mine = $this->driverAt(1);
        $other = $this->driverAt(2);
        $job = $this->paidJob();
        $this->asDriver($mine)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        $this->asDriver($other)->getJson("/api/driver/jobs/{$job->id}")->assertForbidden();
        $this->asDriver($other)->postJson("/api/driver/jobs/{$job->id}/status", ['status' => 'arrived_at_shop'])->assertForbidden();
        $this->asDriver($other)->getJson('/api/driver/jobs/active')->assertOk()->assertJsonPath('data', null);
        $this->asDriver($other)->getJson('/api/driver/jobs')->assertOk()->assertJsonCount(0, 'data');

        // A driver is not the buyer either.
        $this->asDriver($mine)->getJson("/api/delivery/orders/{$job->order_id}")->assertForbidden();
    }

    public function test_work_routes_need_an_approved_driver(): void
    {
        $notADriver = User::factory()->create();
        $this->actingAs($notADriver)->getJson('/api/driver/offers')->assertForbidden()->assertJsonPath('code', 'not_a_driver');

        $pending = DriverProfile::factory()->create();
        $this->actingAs($pending->user)->getJson('/api/driver/offers')->assertForbidden()->assertJsonPath('code', 'driver_not_approved');
        $this->actingAs($pending->user)->patchJson('/api/driver/availability', ['online' => true, 'lat' => 0, 'lng' => 0])->assertForbidden();

        $suspended = $this->driverAt(1);
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->asDriver($suspended)->postJson('/api/driver/location', ['lat' => 0, 'lng' => 0])->assertForbidden();
    }

    public function test_realtime_token_only_for_parties_of_an_active_job(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $buyer = $job->order->buyer;

        $this->actingAs($buyer)->postJson('/api/delivery/realtime-token')->assertForbidden()->assertJsonPath('code', 'no_active_job');

        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        // Firebase is not configured in tests: honest 503 with a polling fallback.
        $this->actingAs($buyer)->postJson('/api/delivery/realtime-token')->assertStatus(503)->assertJsonPath('fallback', 'poll');
        $this->asDriver($driver)->postJson('/api/delivery/realtime-token')->assertStatus(503);
        $this->actingAs(User::factory()->create())->postJson('/api/delivery/realtime-token')->assertForbidden();
    }

    public function test_driver_profile_never_exposes_the_full_national_id(): void
    {
        $driver = $this->driverAt(1);

        $data = $this->asDriver($driver)->getJson('/api/driver/me')->assertOk()->json('data');

        $this->assertSame(substr($driver->national_id_number, -4), $data['national_id_last4']);
        $this->assertStringNotContainsString($driver->national_id_number, json_encode($data));
        $this->assertArrayNotHasKey('national_id_image', $data);
    }
}

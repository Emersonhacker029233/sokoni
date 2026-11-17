<?php

namespace Tests\Feature\Delivery\Bolt;

use App\Models\User;
use App\Services\Delivery\DeliveryActionException;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/** Cancelling a Bolt delivery: the ride is cancelled at Bolt before pickup, never after; the fee is refunded by the agreed rules. */
class BoltCancellationTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_buyer_cancels_before_booking_full_refund_nothing_sent_to_bolt(): void
    {
        $job = $this->paidJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel", ['reason' => 'Changed plans'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.refund.amount', self::FEE)
            ->assertJsonPath('data.refund.status', 'pending');

        $this->assertSame([], $this->boltCancelled);
        $this->assertSame('cancelled', $job->order->fresh()->status);
    }

    public function test_buyer_cancels_while_bolt_is_searching_cancels_the_ride_with_a_full_refund(): void
    {
        $job = $this->bookedJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        $this->assertSame(['1001'], $this->boltCancelled);
        $job->refresh();
        $this->assertSame(self::FEE, (int) $job->refund_amount);
        $this->assertSame(1, $job->events()->where('type', 'provider_ride_cancelled')->count());
    }

    public function test_buyer_cancelling_after_a_driver_is_assigned_keeps_the_cancellation_fee(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        $job->refresh();
        $this->assertSame(['1001'], $this->boltCancelled);
        $this->assertSame(self::FEE - 1000, (int) $job->refund_amount);
        $this->assertSame(1000, $job->events()->where('to_status', 'cancelled')->sole()->meta['kept_amount']);
    }

    public function test_seller_cancelling_the_order_after_assignment_refunds_in_full_and_is_tagged(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'ARRIVED_AT_CLIENT');
        $seller = $job->order->seller->user;

        $this->actingAs($seller)->patchJson("/api/orders/{$job->order_id}/status", ['status' => 'cancelled', 'reason' => 'Out of stock'])->assertOk();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('seller', $job->cancelled_by);
        $this->assertSame(self::FEE, (int) $job->refund_amount);
        $this->assertSame(['1001'], $this->boltCancelled);
        $tag = $job->events()->where('type', DeliveryJobService::SELLER_CANCELLED_AFTER_ASSIGN)->sole();
        $this->assertSame('1001', $tag->meta['ride_id']);
    }

    public function test_nobody_but_an_admin_cancels_once_the_parcel_left_the_shop(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVING_WITH_CLIENT');
        $order = $job->order;

        $this->actingAs($order->buyer)->postJson("/api/delivery/orders/{$order->id}/cancel")->assertStatus(409)->assertJsonPath('code', 'cannot_cancel_in_transit');
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'x'])->assertStatus(422);
        $this->assertSame([], $this->boltCancelled, 'a ride with the parcel in the car is never cancelled at Bolt');

        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        app(DeliveryJobService::class)->cancel($job, $admin, 'admin', 'Driver unreachable, parcel recovered');
        $this->assertSame('cancelled', $job->fresh()->status);
        $this->assertSame([], $this->boltCancelled);
    }

    public function test_a_stale_status_never_lets_an_in_transit_parcel_be_cancelled(): void
    {
        $job = $this->bookedJob();
        // Bolt already has the parcel in the car, but no poll has seen it yet.
        $this->boltState('1001', 'DRIVING_WITH_CLIENT');

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertStatus(409);

        $this->assertSame('on_the_way', $job->fresh()->status);
        $this->assertSame([], $this->boltCancelled);
    }

    public function test_switch_to_pickup_while_searching_cancels_the_ride_and_refunds(): void
    {
        $job = $this->bookedJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/switch-to-pickup")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.refund.amount', self::FEE);

        $this->assertSame(['1001'], $this->boltCancelled);
        $order = $job->order->fresh();
        $this->assertSame('pickup', $order->delivery_method);
        $this->assertSame('ready', $order->status, 'the order stays open for collection');
    }

    public function test_bolt_refusing_the_cancel_still_cancels_locally_and_leaves_a_note_for_admins(): void
    {
        $job = $this->bookedJob();
        foreach (range(1, 3) as $_) {
            $this->boltFailNext('cancel', 500, 'RIDE_BOOKER_API_INTERNAL_ORDER_CANCELLATION_FAILED');
        }

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $failed = $job->events()->where('type', 'provider_cancel_failed')->sole();
        $this->assertSame('RIDE_BOOKER_API_INTERNAL_ORDER_CANCELLATION_FAILED', $failed->meta['x-bolt-error-code']);
    }

    public function test_only_the_buyer_acts_on_the_delivery(): void
    {
        $job = $this->paidJob();

        $this->actingAs($job->order->seller->user)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson("/api/delivery/orders/{$job->order_id}")->assertForbidden();
    }

    public function test_retry_is_refused_where_bolt_cannot_help(): void
    {
        $job = $this->paidJob();
        $this->boltFailNext('estimate', 422, 'RIDE_BOOKER_API_AREA_NOT_SERVICED');
        $job = $this->markReady($job->order);

        $this->expectException(DeliveryActionException::class);
        app(DeliveryJobService::class)->retry($job, $job->order->buyer);
    }
}

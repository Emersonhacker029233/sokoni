<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Services\Delivery\DeliveryJobService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PLAN.md section 6, client-approved 2026-10-29. Buyer cancel fee default TSh 1,000. */
class CancellationTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    private function assignedJob(): array
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        return [$job->fresh(), $driver];
    }

    public function test_buyer_cancel_before_assignment_is_free_with_full_refund(): void
    {
        $offered = $this->driverAt(1);
        $job = $this->paidJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel", ['reason' => 'Nimebadili mawazo'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.refund.amount', $job->fee);

        $job->refresh();
        $this->assertSame(0, $job->earned_amount);
        $this->assertSame($job->fee, $job->refund_amount);
        $this->assertSame('pending', $job->refund_status);
        $this->assertSame('cancelled', $job->order->status);
        $this->assertSame('withdrawn', $job->offers()->where('driver_profile_id', $offered->id)->value('status'));
    }

    public function test_buyer_cancel_after_assignment_pays_the_driver_the_cancellation_fee(): void
    {
        [$job, $driver] = $this->assignedJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        $job->refresh();
        $this->assertSame(1000, $job->earned_amount);
        $this->assertSame($job->fee - 1000, $job->refund_amount);
        $this->assertSame('buyer', $job->cancelled_by);
        $this->assertTrue($this->pushes->to($driver->user)->contains(fn ($p) => ($p['data']['status'] ?? null) === 'cancelled'));
    }

    public function test_seller_cancel_after_assignment_refunds_buyer_in_full_and_compensates_driver(): void
    {
        [$job] = $this->assignedJob();
        $order = $job->order;

        // Existing Sokoni 1.0 order endpoint; the observer cancels the job.
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'Bidhaa imeisha'])->assertOk();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('seller', $job->cancelled_by);
        $this->assertSame($job->fee, $job->refund_amount);
        $this->assertSame(1000, $job->earned_amount);
    }

    public function test_seller_cancel_after_assignment_is_logged_on_the_timeline(): void
    {
        [$job, $driver] = $this->assignedJob();
        $order = $job->order;

        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'Bidhaa imeisha'])->assertOk();

        $event = $job->events()->where('type', DeliveryJobService::SELLER_CANCELLED_AFTER_ASSIGN)->sole();
        $this->assertSame('seller', $event->actor_role);
        $this->assertSame($order->seller->user_id, $event->actor_user_id);
        $this->assertSame('Bidhaa imeisha', $event->note);
        $this->assertSame([
            'reason' => 'seller_cancelled_after_assign',
            'driver_profile_id' => $driver->id,
            'driver_compensation' => 1000,
            'compensation_funded_by' => 'platform',
            'buyer_refund' => $job->fee,
        ], $event->meta);

        // The shared timeline both apps render carries the reason code too.
        $last = collect($this->actingAs($order->buyer)->getJson("/api/delivery/orders/{$order->id}")->assertOk()->json('data.timeline'))->last();
        $this->assertSame('cancelled', $last['status']);
        $this->assertSame('seller', $last['by']);
        $this->assertSame('seller_cancelled_after_assign', $last['reason']);
    }

    public function test_seller_cancel_before_assignment_is_not_tagged(): void
    {
        $job = $this->paidJob();
        $order = $job->order;

        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'x'])->assertOk();

        $this->assertSame('cancelled', $job->fresh()->status);
        $this->assertFalse($job->events()->where('type', DeliveryJobService::SELLER_CANCELLED_AFTER_ASSIGN)->exists());
    }

    public function test_seller_cancel_compensation_can_be_switched_off(): void
    {
        Settings::set('delivery_seller_cancel_compensates_driver', '0');
        [$job] = $this->assignedJob();
        $order = $job->order;

        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'x'])->assertOk();

        $this->assertSame(0, $job->fresh()->earned_amount);
    }

    public function test_unpaid_job_cancel_refunds_nothing(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        $this->assertSame(0, $job->fresh()->refund_amount);
        $this->assertNull($job->fresh()->refund_status);
    }

    public function test_no_driver_found_offers_retry_pickup_or_cancel(): void
    {
        $job = $this->paidJob();
        foreach (range(1, 3) as $i) {
            $this->travel(61)->seconds();
            $this->artisan('delivery:dispatch-tick');
        }
        $this->assertSame('no_driver_found', $job->fresh()->status);

        $buyer = $job->order->buyer;
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_retry', true)
            ->assertJsonPath('data.actions.can_switch_to_pickup', true)
            ->assertJsonPath('data.actions.can_cancel', true);

        // Retry: back into wave 1, no new payment.
        $this->actingAs($buyer)->postJson("/api/delivery/orders/{$job->order_id}/retry")->assertOk();
        $this->assertSame(1, $job->fresh()->dispatch_wave);
        $this->assertSame('paid', $job->fresh()->payment_status);
    }

    public function test_switch_to_pickup_refunds_the_fee_and_converts_the_order(): void
    {
        $job = $this->paidJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/switch-to-pickup")->assertOk();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('switched_to_pickup', $job->cancel_reason);
        $this->assertSame($job->fee, $job->refund_amount);
        $this->assertSame('pickup', $job->order->delivery_method);
        $this->assertSame('pay_on_pickup', $job->order->payment_method);
        $this->assertSame('accepted', $job->order->status, 'The order itself stays alive for pickup.');

        // The seller can now run the normal legacy flow on it.
        $order = $job->order;
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'ready'])->assertOk();
        $this->assertSame(1, DeliveryJob::query()->count(), 'No second job for a pickup order.');
    }

    public function test_payment_arriving_after_cancellation_is_kept_for_refund(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        app(DeliveryJobService::class)->markFeePaid($job, 'LATE-REF');

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame($job->fee, $job->refund_amount);
        $this->assertSame('pending', $job->refund_status);
    }
}

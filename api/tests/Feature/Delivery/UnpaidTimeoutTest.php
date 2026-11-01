<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Client decision 2026-11-01: unpaid jobs auto-cancel after 20 minutes (configurable), via the minute cron. */
class UnpaidTimeoutTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_unpaid_job_is_cancelled_with_its_order_after_twenty_minutes(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $this->assertSame('pending_payment', $job->status);

        $this->travel(19)->minutes();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();
        $this->assertSame('pending_payment', $job->fresh()->status);

        $this->travel(1)->minutes();
        $this->artisan('delivery:dispatch-tick')->expectsOutput('Cancelled 1 unpaid job(s).')->assertSuccessful();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('system', $job->cancelled_by);
        $this->assertSame(DeliveryJobService::PAYMENT_TIMEOUT, $job->cancel_reason);
        $this->assertSame(0, $job->refund_amount);
        $this->assertSame(0, $job->earned_amount);
        $this->assertSame('cancelled', $job->order->status);
        $this->assertNotNull($job->order->cancelled_reason);

        $event = $job->events()->where('type', 'status')->where('to_status', 'cancelled')->sole();
        $this->assertSame('system', $event->actor_role);
        $this->assertSame('payment_timeout', $event->meta['reason']);

        $buyer = $job->order->buyer;
        $expected = __('delivery.push.buyer_payment_expired.body', ['code' => $job->order->code], $buyer->preferredLocale());
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['body'] === $expected));
        $this->assertTrue($this->pushes->to($job->order->seller->user)->contains(fn ($p) => ($p['data']['status'] ?? null) === 'cancelled'));
    }

    public function test_timeout_is_configurable(): void
    {
        Settings::set('delivery_unpaid_timeout_minutes', '5');
        $job = $this->acceptAsSeller($this->placeDispatchOrder());

        $this->travel(5)->minutes();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $this->assertSame('cancelled', $job->fresh()->status);
    }

    public function test_paid_and_cash_jobs_are_never_expired(): void
    {
        $paid = $this->paidJob();

        Settings::set('delivery_fee_cash_enabled', '1');
        $cash = $this->acceptAsSeller($this->placeDispatchOrder(User::factory()->create(['phone' => '+255711000222'])));
        $this->assertSame('pending_driver', $cash->status);

        $this->travel(60)->minutes();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $this->assertNotSame('cancelled', $paid->fresh()->status);
        $this->assertNotSame('cancelled', $cash->fresh()->status);
        $this->assertNotSame('payment_timeout', $paid->fresh()->cancel_reason);
    }

    public function test_payment_confirmed_after_expiry_is_kept_for_refund(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $this->travel(21)->minutes();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        app(DeliveryJobService::class)->markFeePaid($job, 'LATE-REF');

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame($job->fee, $job->refund_amount);
        $this->assertSame('pending', $job->refund_status);
    }

    public function test_tick_does_nothing_while_delivery_is_disabled(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        config(['delivery.enabled' => false]);

        $this->travel(30)->minutes();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $this->assertSame('pending_payment', $job->fresh()->status);
    }
}

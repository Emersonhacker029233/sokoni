<?php

namespace Tests\Feature\Delivery;

use App\Services\Delivery\DeliveryJobService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unpaid delivery fees: the job and its order are cancelled after 20
 * minutes (configurable) by the minute cron, counted from when Bolt's
 * price was set. No driver is involved any more; nothing is booked on Bolt
 * before payment, so there is nothing to cancel there.
 */
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
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertSame('pending_payment', $job->fresh()->status);

        $this->travel(1)->minutes();
        $this->artisan('delivery:tick')->expectsOutput('expired: 1')->assertSuccessful();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('system', $job->cancelled_by);
        $this->assertSame(DeliveryJobService::PAYMENT_TIMEOUT, $job->cancel_reason);
        $this->assertSame(0, $job->refund_amount);
        $this->assertSame('cancelled', $job->order->status);
        $this->assertNotNull($job->order->cancelled_reason);
        $this->assertSame([], $this->boltCreated);
        $this->assertSame([], $this->boltCancelled);

        $event = $job->events()->where('type', 'status')->where('to_status', 'cancelled')->sole();
        $this->assertSame('system', $event->actor_role);
        $this->assertSame('payment_timeout', $event->meta['reason']);

        $buyer = $job->order->buyer;
        $expected = __('delivery.push.buyer_payment_expired.body', ['code' => $job->order->code], $buyer->preferredLocale());
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['body'] === $expected));
        $this->assertTrue($this->pushes->to($job->order->seller->user)->contains(fn ($p) => ($p['data']['status'] ?? null) === 'cancelled'));
    }

    public function test_the_clock_starts_when_the_price_is_set_not_when_the_shop_accepted(): void
    {
        $order = $this->placeDispatchOrder();
        foreach (range(1, 3) as $_) {
            $this->boltFailNext('estimate', 503);
        }
        $job = $this->acceptAsSeller($order);
        $this->assertSame('pending_quote', $job->status);

        // Bolt answers 10 minutes later; the buyer still gets the full 20.
        $this->travel(10)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertSame('pending_payment', $job->fresh()->status);

        $this->travel(19)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertSame('pending_payment', $job->fresh()->status);
    }

    public function test_timeout_is_configurable(): void
    {
        Settings::set('delivery_unpaid_timeout_minutes', '5');
        $job = $this->acceptAsSeller($this->placeDispatchOrder());

        $this->travel(5)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();

        $this->assertSame('cancelled', $job->fresh()->status);
    }

    public function test_paid_jobs_are_never_expired(): void
    {
        $paid = $this->paidJob();

        $this->travel(60)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();

        $this->assertSame('awaiting_ready', $paid->fresh()->status);
    }

    public function test_payment_confirmed_after_expiry_is_kept_for_refund(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $this->travel(21)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();

        app(DeliveryJobService::class)->markFeePaid($job, 'LATE-REF');

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame((int) $job->fee, (int) $job->refund_amount);
        $this->assertSame('pending', $job->refund_status);
    }

    public function test_switching_bolt_off_later_still_lets_existing_deliveries_finish(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        config(['delivery.enabled' => false]); // credentials still in .env

        $this->travel(30)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();

        $this->assertSame('cancelled', $job->fresh()->status);
    }

    public function test_tick_does_nothing_on_a_server_without_bolt(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        config(['delivery.enabled' => false, 'delivery.bolt.client_id' => null, 'delivery.bolt.client_secret' => null]);

        $this->travel(30)->minutes();
        \DB::enableQueryLog();
        $this->artisan('delivery:tick')->assertSuccessful();

        $this->assertSame([], \DB::getQueryLog(), 'not even a query runs');
        $this->assertSame('pending_payment', $job->fresh()->status);
    }
}

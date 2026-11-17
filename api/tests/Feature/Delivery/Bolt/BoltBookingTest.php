<?php

namespace Tests\Feature\Delivery\Bolt;

use App\Services\Delivery\DeliveryJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/**
 * Booking the Bolt ride: only once the fee is paid AND the shop has marked
 * the order ready, always with a fresh fare_id, and with retries/backoff
 * when Bolt has a problem.
 */
class BoltBookingTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_paid_then_ready_books_the_ride_at_once_with_a_fresh_fare(): void
    {
        $job = $this->paidJob();
        $faresBefore = count($this->boltFareIds);
        $this->assertSame([], $this->boltCreated);

        $job = $this->markReady($job->order);

        $this->assertSame('pending_driver', $job->status);
        $this->assertSame('1001', $job->provider_ref);
        $this->assertSame('SEARCHING', $job->provider_state);
        $this->assertNotNull($job->booked_at);
        $this->assertCount(1, $this->boltCreated);
        // A brand-new estimate was made for the booking, and its fare (the cheapest bookable category's) was used.
        $this->assertSame($faresBefore + 3, count($this->boltFareIds));
        $this->assertSame($this->boltFareIds[$faresBefore], $this->boltCreated[0]['fare_id']);
        $this->assertSame('ready', $job->order->fresh()->status);
    }

    public function test_ready_then_paid_books_when_the_payment_lands(): void
    {
        $order = $this->placeDispatchOrder();
        $job = $this->acceptAsSeller($order);
        $this->markReady($order);
        $this->assertSame('pending_payment', $job->fresh()->status);
        $this->assertSame([], $this->boltCreated, 'never book before the fee is paid');

        $job = app(DeliveryJobService::class)->markFeePaid($job, 'REF');

        $this->assertSame('pending_driver', $job->status);
        $this->assertCount(1, $this->boltCreated);
    }

    public function test_a_fare_id_is_never_reused_across_bookings(): void
    {
        $first = $this->bookedJob();
        $second = $this->bookedJob();

        $used = array_column($this->boltCreated, 'fare_id');
        $this->assertCount(2, $used);
        $this->assertCount(2, array_unique($used));
        $this->assertNotSame($first->provider_ref, $second->provider_ref);
    }

    public function test_an_expired_or_used_fare_is_estimated_again_once(): void
    {
        $job = $this->paidJob();
        $this->boltFailNext('create', 400, 'RIDE_BOOKER_API_INVALID_FARE_ID');

        $job = $this->markReady($job->order);

        $this->assertSame('pending_driver', $job->status);
        $this->assertCount(1, $this->boltCreated);
        // Two booking estimates of 3 categories each: the refused fare came from the first, the ride from the second.
        $this->assertSame($this->boltFareIds[count($this->boltFareIds) - 3], $this->boltCreated[0]['fare_id']);
    }

    public function test_area_not_serviced_at_booking_tells_the_buyer_and_offers_pickup(): void
    {
        $job = $this->paidJob();
        $this->boltFailNext('estimate', 422, 'RIDE_BOOKER_API_AREA_NOT_SERVICED');

        $job = $this->markReady($job->order);

        $this->assertSame('no_driver_found', $job->status);
        $this->assertSame('area_not_serviced', $job->failure_reason);
        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.actions.can_retry', false)
            ->assertJsonPath('data.actions.can_switch_to_pickup', true)
            ->assertJsonPath('data.actions.can_cancel', true);
        $buyer = $job->order->buyer;
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['title'] === __('delivery.push.buyer_not_serviced.title', [], $buyer->preferredLocale())));
    }

    public function test_bolt_errors_are_retried_with_backoff_by_the_minute_cron(): void
    {
        $job = $this->paidJob();
        foreach (range(1, 3) as $_) {
            $this->boltFailNext('create', 500, 'RIDE_BOOKER_API_INTERNAL_ORDER_CREATION_FAILED');
        }

        $job = $this->markReady($job->order);
        $this->assertSame('awaiting_ready', $job->status);
        $this->assertSame(1, $job->booking_attempts);
        $this->assertTrue($job->next_booking_at->between(now()->addSeconds(55), now()->addSeconds(65)));
        $error = $job->events()->where('type', 'provider_error')->sole();
        $this->assertSame('RIDE_BOOKER_API_INTERNAL_ORDER_CREATION_FAILED', $error->meta['x-bolt-error-code']);
        $this->assertSame('trk-create', $error->meta['x-bolt-tracking']);

        // Not yet due.
        $this->travel(30)->seconds();
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertSame('awaiting_ready', $job->fresh()->status);

        $this->travel(31)->seconds();
        $this->artisan('delivery:tick')->expectsOutput('booked: 1')->assertSuccessful();
        $this->assertSame('pending_driver', $job->fresh()->status);
    }

    public function test_after_the_last_try_the_buyer_is_told_no_driver_could_be_booked(): void
    {
        config(['delivery.bolt.max_booking_attempts' => 2]);
        $job = $this->paidJob();
        foreach (range(1, 6) as $_) {
            $this->boltFailNext('create', 503);
        }

        $this->markReady($job->order);
        $this->travel(2)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();

        $job->refresh();
        $this->assertSame('no_driver_found', $job->status);
        $this->assertSame('booking_failed', $job->failure_reason);
        $this->assertSame(2, $job->booking_attempts);
        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.actions.can_retry', true);
    }

    public function test_a_request_bolt_will_never_accept_gives_up_at_once(): void
    {
        $job = $this->paidJob();
        $this->boltFailNext('create', 400, 'RIDE_BOOKER_API_VALIDATION', ['validation_errors' => [['property' => 'user', 'error' => 'landline']]]);

        $job = $this->markReady($job->order);

        $this->assertSame('no_driver_found', $job->status);
        $this->assertSame('booking_failed', $job->failure_reason);
        $this->assertSame([], $this->boltCreated);
    }

    public function test_a_rejected_bolt_account_alerts_support(): void
    {
        Log::spy();
        $job = $this->paidJob();
        $this->boltFailNext('create', 403, 'RIDE_BOOKER_API_FORBIDDEN');

        $this->markReady($job->order);

        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, "Bolt refused Sokoni's account"));
        $this->assertSame('awaiting_ready', $job->fresh()->status, 'kept for a retry once the account is fixed');
    }

    public function test_retry_after_no_driver_books_again_with_a_new_fare(): void
    {
        $job = $this->bookedJob();
        $job = $this->boltMoves($job, 'NO_DRIVER_FOUND');
        $this->assertSame('no_driver_found', $job->status);
        $this->assertSame('no_driver_found', $job->failure_reason);

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_driver');

        $job->refresh();
        $this->assertSame('1002', $job->provider_ref);
        $this->assertCount(2, $this->boltCreated);
        $this->assertNotSame($this->boltCreated[0]['fare_id'], $this->boltCreated[1]['fare_id']);
        $this->assertSame('paid', $job->payment_status, 'the fee carries over');
        $this->assertSame('1001', $job->events()->where('note', 'retry')->sole()->meta['previous_ride_id']);
    }
}

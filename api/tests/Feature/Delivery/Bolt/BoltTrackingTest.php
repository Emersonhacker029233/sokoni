<?php

namespace Tests\Feature\Delivery\Bolt;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/**
 * Every Bolt ride state, as the 10-second tracker sees it, and what the
 * buyer and the shop see in return.
 */
class BoltTrackingTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_a_normal_ride_from_searching_to_completed(): void
    {
        $job = $this->bookedJob();
        $buyer = $job->order->buyer;
        $seller = $job->order->seller->user;

        $job = $this->boltMoves($job, 'DRIVER_ON_ROUTE_TO_CLIENT');
        $this->assertSame('driver_assigned', $job->status);
        $this->assertSame('Seyf Driver', $job->provider_driver['name']);
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.status', 'driver_assigned')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.driver.name', 'Seyf Driver')
            ->assertJsonPath('data.driver.phone', '+255700667788')
            ->assertJsonPath('data.driver.plate', 'T123ABC')
            ->assertJsonPath('data.driver.model', 'Toyota IST')
            ->assertJsonPath('data.driver.photo_url', 'https://driver-picture-url/')
            ->assertJsonPath('data.tracking_url', 'https://bolt.eu/track/abc');
        $this->actingAs($seller)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.driver.phone', '+255700667788')
            ->assertJsonPath('data.tracking_url', 'https://bolt.eu/track/abc');
        $this->assertTrue($this->pushes->to($seller)->contains(fn ($p) => str_contains($p['body'], 'T123ABC')));

        $job = $this->boltMoves($job, 'ARRIVED_AT_CLIENT');
        $this->assertSame('arrived_at_shop', $job->status);

        $job = $this->boltMoves($job, 'DRIVING_WITH_CLIENT');
        $this->assertSame('on_the_way', $job->status);
        $this->assertNotNull($job->picked_up_at);
        $this->actingAs($seller)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.driver.phone', null)
            ->assertJsonPath('data.tracking_url', null);
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.actions.can_cancel', false)
            ->assertJsonPath('data.actions.can_switch_to_pickup', false);

        $this->boltReceipt = ['amount' => 5900, 'currency_code' => 'TZS', 'invoice_url' => 'https://invoice', 'fare_breakdown' => [['type' => 'ride_fee', 'amount' => 5900]]];
        $job = $this->boltMoves($job, 'COMPLETED');
        $this->assertSame('delivered', $job->status);
        $this->assertSame('completed', $job->order->status);
        $this->assertSame('paid', $job->order->payment_status);
        $this->assertSame(5900.0, $job->final_cost);
        $this->assertSame(500.0, $job->cost_difference, 'customer paid 6,400, Bolt charged 5,900');
        $this->assertSame('https://invoice', $job->receipt['invoice_url']);
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.driver.phone', null)
            ->assertJsonPath('data.tracking_url', null);

        // Completing the order unlocks the existing order-gated review.
        $this->assertTrue($job->order->isCompleted());

        $timeline = collect($this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")->json('data.timeline'))->pluck('status')->all();
        $this->assertSame(['pending_quote', 'pending_payment', 'awaiting_ready', 'pending_driver', 'driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered'], $timeline);
    }

    /**
     * Flat delivery fee: the buyer paid one fixed fee. Bolt's receipt, the
     * final cost and the difference are for admins only, never in what the
     * buyer's or the shop's app receives.
     */
    public function test_the_buyer_and_the_shop_never_see_bolts_receipt_or_final_cost(): void
    {
        $job = $this->bookedJob();
        $this->boltReceipt = ['amount' => 5873, 'currency_code' => 'TZS', 'invoice_url' => 'https://invoice/abc', 'fare_breakdown' => [['type' => 'ride_fee', 'amount' => 5873]]];
        foreach (['DRIVER_ON_ROUTE_TO_CLIENT', 'DRIVING_WITH_CLIENT', 'COMPLETED'] as $state) {
            $job = $this->boltMoves($job, $state);
        }
        $this->assertSame(5873.0, $job->final_cost, 'the admin record has the receipt');
        $this->assertSame(527.0, $job->cost_difference);

        foreach ([$job->order->buyer, $job->order->seller->user] as $viewer) {
            $response = $this->actingAs($viewer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk();
            foreach (['final_cost', 'cost_difference', 'estimate', 'min', 'max', 'receipt_amount'] as $key) {
                $this->assertArrayNotHasKey($key, $response->json('data'), "{$key} reached the app");
            }
            $values = Arr::dot($response->json('data'));
            $this->assertNotContains(5873, array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $values), "Bolt's receipt amount reached the app");
            $this->assertNotContains(527, array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $values), 'the cost difference reached the app');
            $this->assertStringNotContainsString('invoice', $response->getContent());
        }

        // The buyer's own payment receipt is the fixed fee they paid.
        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.fee', 6400);
    }

    public function test_bolt_skipping_states_between_polls_still_lands_correctly(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'COMPLETED');

        $this->assertSame('delivered', $job->status);
        $this->assertNotNull($job->picked_up_at);
    }

    public function test_a_receipt_that_is_not_ready_yet_is_collected_later_by_the_minute_cron(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'COMPLETED');
        $this->assertNull($job->final_cost);
        $this->assertNotNull($job->receipt_checked_at);

        $this->boltReceipt = ['amount' => 7000, 'currency_code' => 'TZS', 'fare_breakdown' => []];
        $this->travel(6)->minutes();
        $this->artisan('delivery:tick')->expectsOutput('receipts: 1')->assertSuccessful();

        $job->refresh();
        $this->assertSame(7000.0, $job->final_cost);
        $this->assertSame(-600.0, $job->cost_difference, 'Bolt charged more than the customer paid: Sokoni absorbs it, admins see it');
    }

    public function test_a_driver_who_drops_the_ride_sends_it_back_to_searching(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $job = $this->boltMoves($job, 'SEARCHING');

        $this->assertSame('pending_driver', $job->status);
        $this->assertNull($job->provider_driver);
        $buyer = $job->order->buyer;
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['title'] === __('delivery.push.buyer_driver_changed.title', [], $buyer->preferredLocale())));
    }

    /** @return array<string, array{string, string, string, bool}> */
    public static function problemStates(): array
    {
        return [
            'no driver' => ['NO_DRIVER_FOUND', 'no_driver_found', 'no_driver_found', true],
            'shop did not hand over' => ['CLIENT_DID_NOT_SHOW', 'failed', 'shop_no_show', true],
            'payment booking failed' => ['PAYMENT_BOOKING_FAILED', 'failed', 'payment_booking_failed', true],
            'cancelled by Bolt' => ['CANCELLED', 'failed', 'provider_cancelled', true],
            'cancelled by client' => ['CLIENT_CANCELLED', 'failed', 'client_cancelled', true],
        ];
    }

    #[DataProvider('problemStates')]
    public function test_problem_states_before_pickup_let_the_buyer_book_again_switch_or_cancel(string $bolt, string $status, string $reason, bool $retryable): void
    {
        $job = $this->boltMoves($this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT'), $bolt);

        $this->assertSame($status, $job->status);
        $this->assertSame($reason, $job->failure_reason);
        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.actions.can_retry', $retryable)
            ->assertJsonPath('data.actions.can_switch_to_pickup', true)
            ->assertJsonPath('data.actions.can_cancel', true);
    }

    public function test_payment_booking_failed_alerts_support(): void
    {
        Log::spy();
        $this->boltMoves($this->bookedJob(), 'PAYMENT_BOOKING_FAILED');

        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'payment_booking_failed'));
    }

    public function test_bolt_cancelling_with_the_parcel_in_the_car_is_for_support_not_a_button(): void
    {
        Log::spy();
        $job = $this->boltMoves($this->bookedJob(), 'DRIVING_WITH_CLIENT');
        $job = $this->boltMoves($job, 'CANCELLED');

        $this->assertSame('failed', $job->status);
        $this->assertSame('provider_cancelled_in_transit', $job->failure_reason);
        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.actions.can_retry', false)
            ->assertJsonPath('data.actions.can_switch_to_pickup', false)
            ->assertJsonPath('data.actions.can_cancel', false);
        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertStatus(409);
        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'provider_cancelled_in_transit'));
    }

    public function test_ride_details_are_read_at_most_once_per_ten_seconds_per_ride(): void
    {
        $job = $this->bookedJob();
        $buyer = $job->order->buyer;
        $count = fn () => Http::recorded(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/rides?'))->count();

        $this->travel(11)->seconds();
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk();
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->assertSame(1, $count());

        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->assertSame(2, $count());
    }

    public function test_the_buyers_poll_picks_up_a_new_state_without_waiting_for_the_cron(): void
    {
        $job = $this->bookedJob();
        $this->boltState('1001', 'ARRIVED_AT_CLIENT');
        $this->travel(11)->seconds();

        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertJsonPath('data.status', 'arrived_at_shop');
    }

    public function test_finished_rides_are_never_polled(): void
    {
        $this->boltMoves($this->bookedJob(), 'COMPLETED');
        $before = Http::recorded()->count();

        $this->travel(1)->minutes();
        $this->artisan('delivery:track')->assertSuccessful();

        $this->assertSame($before, Http::recorded()->count());
    }
}

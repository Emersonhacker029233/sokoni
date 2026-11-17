<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/** Checkout opts into Bolt delivery; the shop's acceptance prices it from Bolt's estimate. */
class CheckoutAndJobCreationTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->setUpDelivery();
    }

    private function legacyDeliveryCheckout(User $buyer, Product $product, array $extra = [])
    {
        return $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'delivery',
            'address' => '123 Uhuru Street',
            'payment_method' => 'cash_on_delivery',
            ...$extra,
        ]);
    }

    public function test_flag_off_ignores_fulfilment_and_keeps_the_flat_fee(): void
    {
        config(['delivery.enabled' => false]);
        $product = Product::factory()->create(['seller_id' => $this->shop()->id, 'price' => 10000]);

        $response = $this->legacyDeliveryCheckout(User::factory()->create(), $product, [
            'fulfilment' => 'dispatch', 'delivery_lat' => self::DROP_LAT, 'delivery_lng' => self::DROP_LNG,
            'delivery_phone' => 'not-a-phone',
        ])->assertCreated();

        $response->assertJsonPath('data.delivery_fee', 3000);
        $response->assertJsonPath('data.total', 13000);
        $response->assertJsonMissingPath('data.fulfilment');
        $response->assertJsonMissingPath('data.delivery_phone');
        $this->assertNull(Order::query()->sole()->fulfilment);
    }

    public function test_flag_on_but_sokoni_1_0_request_is_the_old_flow_with_no_bolt_call(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop()->id, 'price' => 10000]);

        $id = $this->legacyDeliveryCheckout(User::factory()->create(), $product)
            ->assertCreated()
            ->assertJsonPath('data.delivery_fee', 3000)
            ->assertJsonPath('data.total', 13000)
            ->assertJsonMissingPath('data.fulfilment')
            ->json('data.id');

        $order = Order::query()->findOrFail($id);
        foreach (['accepted', 'ready', 'completed'] as $status) {
            $this->actingAs($order->seller->user)->patchJson("/api/orders/{$id}/status", ['status' => $status])->assertOk();
        }

        $this->assertSame(0, DeliveryJob::query()->count());
        $this->assertSame([], $this->pushes->sent);
        $this->assertSame([], $this->boltFareIds, 'Bolt is never asked about an ordinary order');
    }

    public function test_bolt_checkout_keeps_the_fee_out_of_the_sellers_total(): void
    {
        $order = $this->placeDispatchOrder();

        $this->assertSame('dispatch', $order->fulfilment);
        $this->assertSame(0, (int) $order->delivery_fee);
        $this->assertSame(40000, (int) $order->total);
        $this->assertNull($order->deliveryJob, 'No job until the seller accepts.');
    }

    public function test_bolt_checkout_needs_a_map_pin(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop()->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, ['fulfilment' => 'dispatch'])
            ->assertStatus(422)->assertJsonValidationErrors('delivery_lat');
    }

    public function test_bolt_checkout_rejects_drop_offs_beyond_the_max_distance(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop()->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, [
            'fulfilment' => 'dispatch', 'delivery_lat' => self::SHOP_LAT + 0.12, 'delivery_lng' => self::SHOP_LNG,
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_lat');
        $this->assertSame([], $this->boltFareIds, 'too far is decided before asking Bolt');
    }

    public function test_bolt_checkout_rejects_shops_without_a_location(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop(['lat' => null, 'lng' => null])->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, [
            'fulfilment' => 'dispatch', 'delivery_lat' => self::DROP_LAT, 'delivery_lng' => self::DROP_LNG,
        ])->assertStatus(422)->assertJsonValidationErrors('fulfilment');
    }

    public function test_bolt_checkout_rejects_an_area_bolt_does_not_serve(): void
    {
        $this->boltFailNext('estimate', 422, 'RIDE_BOOKER_API_AREA_NOT_SERVICED');
        $product = Product::factory()->create(['seller_id' => $this->shop()->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, [
            'fulfilment' => 'dispatch', 'delivery_lat' => self::DROP_LAT, 'delivery_lng' => self::DROP_LNG,
        ])->assertStatus(422)->assertJsonPath('errors.delivery_lat.0', __('delivery.errors.area_not_serviced'));
    }

    public function test_bolt_being_down_at_checkout_does_not_block_the_order(): void
    {
        foreach (range(1, 3) as $_) {
            $this->boltFailNext('estimate', 503);
        }

        $order = $this->placeDispatchOrder();

        $this->assertSame('dispatch', $order->fulfilment);
    }

    public function test_seller_acceptance_prices_the_job_from_the_cheapest_bolt_category_with_an_eta(): void
    {
        $order = $this->placeDispatchOrder();
        $job = $this->acceptAsSeller($order);

        // "boda" is cheaper but has no ETA; "bolt_send" (5,200 - 6,400) is the cheapest bookable one.
        $this->assertSame('pending_payment', $job->status);
        $this->assertSame('bolt', $job->provider);
        $this->assertSame('bolt_send', $job->provider_category);
        $this->assertSame(self::FEE, (int) $job->fee, 'the buyer pays the upper bound');
        $this->assertSame(5200.0, $job->estimate_min);
        $this->assertSame(6400.0, $job->estimate_max);
        $this->assertSame('TZS', $job->currency);
        $this->assertSame('unpaid', $job->payment_status);
        $this->assertNotNull($job->pending_payment_at);
        $this->assertNull($job->provider_ref, 'nothing is booked before payment and readiness');
        $this->assertSame([], $this->boltCreated);
        $this->assertSame(self::SHOP_LAT, $job->pickup_lat);
        $this->assertSame('Mtaa wa Uhuru 12', $job->dropoff_address);

        $buyerPush = $this->pushes->to($order->buyer)->last();
        $this->assertSame(__('delivery.push.buyer_pay_fee.title', ['code' => $order->code], $order->buyer->preferredLocale()), $buyerPush['title']);
        $this->assertSame('pending_payment', $buyerPush['data']['status']);
    }

    public function test_an_upper_bound_with_cents_is_rounded_up_to_a_whole_shilling(): void
    {
        $this->boltEstimates = [['vehicle_category' => 'send', 'eta' => 120, 'price' => ['minimum_amount' => 4100.2, 'maximum_amount' => 5300.4, 'currency_code' => 'TZS']]];

        $job = $this->acceptAsSeller($this->placeDispatchOrder());

        $this->assertSame(5301, (int) $job->fee);
    }

    public function test_no_bolt_category_with_an_eta_tells_the_buyer_at_once(): void
    {
        $order = $this->placeDispatchOrder();
        $this->boltEstimates = array_map(fn ($row) => [...$row, 'eta' => null], $this->boltEstimates);

        $job = $this->acceptAsSeller($order);

        $this->assertSame('no_driver_found', $job->status);
        $this->assertSame('no_driver_available', $job->failure_reason);
        $this->assertSame(0, (int) $job->fee);
        $this->actingAs($order->buyer)->getJson("/api/delivery/orders/{$order->id}")
            ->assertJsonPath('data.actions.can_pay', false)
            ->assertJsonPath('data.actions.can_retry', true)
            ->assertJsonPath('data.actions.can_switch_to_pickup', true);
    }

    public function test_bolt_down_at_acceptance_is_retried_by_the_minute_cron(): void
    {
        $order = $this->placeDispatchOrder();
        foreach (range(1, 3) as $_) {
            $this->boltFailNext('estimate', 500, 'RIDE_BOOKER_API_INTERNAL');
        }

        $job = $this->acceptAsSeller($order);
        $this->assertSame('pending_quote', $job->status);
        $this->assertSame(1, $job->booking_attempts);
        $this->assertNotNull($job->next_booking_at);
        $this->assertSame('accepted', $order->fresh()->status, "Bolt being down never fails the seller's tap");

        $this->travel(61)->seconds();
        $this->artisan('delivery:tick')->assertSuccessful();

        $job->refresh();
        $this->assertSame('pending_payment', $job->status);
        $this->assertSame(self::FEE, (int) $job->fee);
        $this->assertSame(1, $job->events()->where('type', 'provider_error')->count());
    }

    public function test_verified_payment_waits_for_the_shop_and_is_idempotent(): void
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $service = app(DeliveryJobService::class);

        $service->markFeePaid($job, 'REF-1');
        $service->markFeePaid($job, 'REF-1');

        $job->refresh();
        $this->assertSame('awaiting_ready', $job->status);
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame(1, $job->events()->where('to_status', 'awaiting_ready')->count());
        $this->assertSame([], $this->boltCreated, 'the shop has not marked it ready yet');
    }

    public function test_seller_cannot_complete_a_bolt_order_while_the_delivery_is_open(): void
    {
        $job = $this->bookedJob();
        $order = $job->order;

        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'completed'])->assertStatus(422);
    }

    public function test_order_resource_shows_fulfilment_only_on_bolt_orders(): void
    {
        $order = $this->placeDispatchOrder(extra: ['delivery_phone' => '+255765000123']);

        $this->actingAs($order->buyer)->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.fulfilment', 'dispatch')
            ->assertJsonPath('data.delivery_phone', '+255765000123');
        $this->actingAs($order->seller->user)->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.fulfilment', 'dispatch')
            ->assertJsonMissingPath('data.delivery_phone');

        $plain = Order::factory()->create(['buyer_id' => $order->buyer_id]);
        $this->actingAs($order->buyer)->getJson("/api/orders/{$plain->id}")->assertOk()->assertJsonMissingPath('data.fulfilment');
    }
}

<?php

namespace Tests\Feature\Delivery;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryNotifier;
use App\Services\Delivery\LogDeliveryNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * API contract for the Sokoni 1.1 customer app (Bolt delivery), and the
 * guarantees that Sokoni 1.0 and sellers keep. The Flutter tests in
 * app/test/data parse the same shapes.
 */
class CustomerAppContractTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
        config(['payments.provider' => 'fake']);
    }

    private function dispatchOrderWithPhone(string $phone = '+255765000123'): Order
    {
        $buyer = User::factory()->create(['phone' => '+255711000111', 'email' => 'buyer@example.com', 'name' => 'Asha Mteja']);
        $shop = $this->shop();
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 20000]);

        $id = $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'delivery',
            'address' => 'Mtaa wa Uhuru 12, Kariakoo',
            'delivery_lat' => self::DROP_LAT,
            'delivery_lng' => self::DROP_LNG,
            'delivery_phone' => $phone,
            'notes' => 'Blue gate',
            'payment_method' => 'cash_on_delivery',
            'fulfilment' => 'dispatch',
        ])->assertCreated()
            ->assertJsonPath('data.fulfilment', 'dispatch')
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.delivery_phone', $phone)
            // Regression (2026-11-04): these were null on create and the
            // 1.1 app failed to parse the order it had just placed.
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.code', fn ($code) => is_string($code) && $code !== '')
            ->json('data.id');

        return Order::query()->findOrFail($id);
    }

    public function test_config_tells_the_app_how_the_bolt_fee_is_paid(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/delivery/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.provider', 'bolt')
            ->assertJsonPath('data.unpaid_timeout_minutes', 20)
            ->assertJsonPath('data.payment.mode', 'online')
            ->assertJsonPath('data.payment.online_available', true)
            ->assertJsonPath('data.payment.covers', ['delivery_fee'])
            ->assertJsonPath('data.payment.goods_paid_to', 'shop')
            ->assertJsonPath('data.tracking.poll_seconds', 10)
            ->assertJsonPath('data.tracking.location_poll_seconds', 5)
            ->assertJsonPath('data.returns_enabled', false)
            ->assertJsonStructure(['data' => ['max_distance_km', 'payment' => ['channels', 'sandbox']]]);
    }

    public function test_config_404s_when_delivery_is_off(): void
    {
        config(['delivery.enabled' => false]);
        $this->actingAs(User::factory()->create())->getJson('/api/delivery/config')->assertNotFound();
    }

    /** Flat delivery fee: checkout gets the one fixed fee, never Bolt's own price range. */
    public function test_quote_returns_the_fixed_fee_and_not_bolts_range(): void
    {
        $shop = $this->shop();
        $quote = $this->actingAs(User::factory()->create())
            ->getJson('/api/delivery/quote?'.http_build_query(['seller_id' => $shop->id, 'lat' => self::DROP_LAT, 'lng' => self::DROP_LNG]))
            ->assertOk()
            ->assertJsonPath('data.provider', 'bolt')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.fee', self::FEE)
            ->assertJsonPath('data.category', 'bolt_send')
            ->assertJsonPath('data.eta_seconds', 240)
            ->assertJsonStructure(['data' => ['distance_km']])
            ->json('data');

        foreach (['min', 'max', 'estimate', 'fare_id', 'final_cost', 'cost_difference'] as $key) {
            $this->assertArrayNotHasKey($key, $quote, "{$key} reached checkout");
        }
        $this->assertStringNotContainsString('5200', json_encode($quote), "Bolt's lower estimate reached checkout");
    }

    public function test_quote_explains_why_delivery_is_unavailable(): void
    {
        $buyer = User::factory()->create();
        $query = fn ($shop) => '/api/delivery/quote?'.http_build_query(['seller_id' => $shop->id, 'lat' => self::DROP_LAT, 'lng' => self::DROP_LNG]);

        $this->boltFailNext('estimate', 422, 'RIDE_BOOKER_API_AREA_NOT_SERVICED');
        $this->actingAs($buyer)->getJson($query($this->shop()))->assertStatus(422)->assertJsonPath('code', 'area_not_serviced');

        foreach (range(1, 3) as $_) {
            $this->boltFailNext('estimate', 500);
        }
        $this->actingAs($buyer)->getJson($query($this->shop(['shop_name' => 'Duka B'])))->assertStatus(503)->assertJsonPath('code', 'quote_unavailable');

        $this->actingAs($buyer)->getJson($query($this->shop(['lat' => null, 'lng' => null])))->assertStatus(422)->assertJsonPath('code', 'shop_has_no_location');
    }

    public function test_sokoni_1_0_checkout_is_unchanged_and_the_seller_still_sees_the_buyer_phone(): void
    {
        $buyer = User::factory()->create(['phone' => '+255711000999']);
        $shop = $this->shop();
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 20000]);

        $id = $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'delivery',
            'address' => 'Mtaa wa Uhuru 12',
            'payment_method' => 'cash_on_delivery',
        ])->assertCreated()
            ->assertJsonMissingPath('data.fulfilment')
            ->assertJsonPath('data.delivery_fee', 3000)
            ->assertJsonPath('data.total', 23000)
            ->assertJsonMissingPath('data.delivery_phone')
            ->json('data.id');

        $this->actingAs($shop->user)->getJson("/api/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.buyer.phone', '+255711000999');
    }

    public function test_the_seller_never_gets_the_buyers_contact_details_on_a_bolt_order(): void
    {
        $order = $this->dispatchOrderWithPhone();
        $seller = $order->seller->user;

        $this->actingAs($seller)->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.buyer.name', $order->buyer->name)
            ->assertJsonPath('data.buyer.phone', null)
            ->assertJsonPath('data.buyer.email', null)
            ->assertJsonMissingPath('data.delivery_phone');

        $this->actingAs($seller)->getJson('/api/shop/orders')
            ->assertOk()
            ->assertJsonPath('data.0.buyer.phone', null);

        $conversation = Conversation::query()->where('order_id', $order->id)->sole();
        $this->actingAs($seller)->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.buyer.phone', null);

        // The buyer still sees their own details.
        $this->actingAs($order->buyer)->getJson("/api/orders/{$order->id}")
            ->assertJsonPath('data.buyer.phone', '+255711000111')
            ->assertJsonPath('data.delivery_phone', '+255765000123');

        // And the seller's delivery view has no fee, payment or drop-off.
        $job = $this->acceptAsSeller($order);
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST');
        $seller->refresh();
        $view = $this->actingAs($seller)->getJson("/api/delivery/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.fee_paid', true)
            ->json('data');
        foreach (['fee', 'estimate', 'payment', 'dropoff', 'amount_due', 'refund', 'actions', 'final_cost', 'cost_difference', 'live_location'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $view, "seller view leaks {$hidden}");
        }
    }

    public function test_bolts_driver_is_told_the_order_code_and_who_to_hand_it_to(): void
    {
        $order = $this->dispatchOrderWithPhone('+255765000123');
        $job = $this->acceptAsSeller($order);
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST');
        $this->markReady($order);

        $created = $this->boltCreated[0];
        $this->assertSame(['name' => 'Duka la Juma', 'phone' => $order->seller->user->phone], $created['user'], 'the shop is the rider');
        $this->assertStringContainsString($order->code, $created['note_to_driver']);
        $this->assertStringContainsString('Asha Mteja', $created['note_to_driver']);
        $this->assertStringContainsString('+255765000123', $created['note_to_driver'], 'the phone given at checkout, not the account phone');
        $this->assertStringContainsString('Collect parcel from shop, hand to customer', $created['note_to_driver']);
        $this->assertSame([
            ['lat' => self::SHOP_LAT, 'lng' => self::SHOP_LNG],
            ['lat' => self::DROP_LAT, 'lng' => self::DROP_LNG],
        ], $created['stops']);

        $this->actingAs($order->buyer)->getJson("/api/delivery/orders/{$order->id}")
            ->assertJsonPath('data.dropoff.phone', '+255765000123');
    }

    public function test_buyer_view_has_every_field_the_order_screen_reads(): void
    {
        $job = $this->acceptAsSeller($this->dispatchOrderWithPhone());

        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertOk()
            ->assertJsonPath('data.provider', 'bolt')
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.amount_due', self::FEE)
            ->assertJsonMissingPath('data.estimate')
            ->assertJsonMissingPath('data.final_cost')
            ->assertJsonMissingPath('data.cost_difference')
            ->assertJsonPath('data.payment.goods_total', 20000)
            ->assertJsonPath('data.payment.delivery_fee', self::FEE)
            ->assertJsonPath('data.payment.total', 20000 + self::FEE)
            ->assertJsonPath('data.payment.attempt', null)
            ->assertJsonPath('data.actions.can_pay', true)
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.tracking_url', null)
            ->assertJsonStructure(['data' => [
                'id', 'order_id', 'order_code', 'provider', 'status', 'is_active', 'distance_km', 'currency', 'fee',
                'payment_status', 'amount_due',
                'pickup' => ['shop_name', 'lat', 'lng'],
                'dropoff' => ['lat', 'lng', 'address', 'phone'],
                'payment' => ['mode', 'status', 'amount_due', 'covers', 'goods_paid_to', 'goods_total', 'delivery_fee',
                    'total', 'deadline', 'online_available', 'sandbox', 'channels', 'attempt', 'receipt'],
                'driver', 'tracking_url', 'refund',
                'actions' => ['can_pay', 'can_cancel', 'can_retry', 'can_switch_to_pickup'],
                'timeline', 'cancelled_by', 'cancel_reason', 'failure_reason', 'created_at',
            ]]);

        // The deadline is the unpaid timeout counted from when the price was set.
        $deadline = $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")->json('data.payment.deadline');
        $this->assertEquals($job->pending_payment_at->addMinutes(20)->toIso8601String(), Carbon::parse($deadline)->toIso8601String());
    }

    public function test_every_status_change_lands_in_the_customer_inbox_with_the_order_id(): void
    {
        $this->app->instance(DeliveryNotifier::class, new LogDeliveryNotifier);
        $order = $this->dispatchOrderWithPhone();
        $job = $this->acceptAsSeller($order);
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST');

        $buyerInbox = $order->buyer->appNotifications()->get();
        $this->assertTrue($buyerInbox->contains(fn ($n) => ($n->data['status'] ?? null) === 'pending_payment'));
        $this->assertTrue($buyerInbox->contains(fn ($n) => ($n->data['status'] ?? null) === 'awaiting_ready'));
        $this->assertTrue($buyerInbox->every(fn ($n) => ($n->data['order_id'] ?? null) === $order->id || ! isset($n->data['type'])));

        $sellerInbox = $order->seller->user->appNotifications()->get();
        $this->assertTrue($sellerInbox->contains(fn ($n) => ($n->data['type'] ?? null) === 'delivery_status' && ($n->data['status'] ?? null) === 'awaiting_ready'));

        $this->actingAs($order->buyer)->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('data.0.payload.order_id', $order->id);
    }

    public function test_the_seller_hears_when_the_buyer_cancels_but_not_about_their_own_cancel(): void
    {
        $job = $this->paidJob();
        $seller = $job->order->seller->user;

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();
        $this->assertTrue($this->pushes->to($seller)->contains(fn ($p) => ($p['data']['status'] ?? null) === 'cancelled'));

        $other = $this->paidJob(User::factory()->create(['phone' => '+255711000333']));
        $otherSeller = $other->order->seller->user;
        $this->actingAs($otherSeller)->patchJson("/api/orders/{$other->order_id}/status", ['status' => 'cancelled', 'reason' => 'Out of stock'])->assertOk();
        $this->assertFalse($this->pushes->to($otherSeller)->contains(fn ($p) => ($p['data']['status'] ?? null) === 'cancelled'));
    }

    public function test_delivery_phone_must_be_a_tanzanian_mobile_number(): void
    {
        $buyer = User::factory()->create();
        $shop = $this->shop();
        $product = Product::factory()->create(['seller_id' => $shop->id]);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'delivery', 'address' => 'x', 'delivery_lat' => self::DROP_LAT, 'delivery_lng' => self::DROP_LNG,
            'delivery_phone' => '0754123456', 'payment_method' => 'cash_on_delivery', 'fulfilment' => 'dispatch',
        ])->assertUnprocessable()->assertJsonValidationErrors('delivery_phone');
    }
}

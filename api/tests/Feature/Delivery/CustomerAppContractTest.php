<?php

namespace Tests\Feature\Delivery;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryNotifier;
use App\Services\Delivery\LogDeliveryNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API contract for the Sokoni 1.1 customer app (delivery flow), and the
 * guarantees that Sokoni 1.0 and sellers keep. The Flutter tests in
 * app/test/data/repositories/delivery_repository_test.dart parse the same
 * shapes.
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

    private function dispatchOrderWithPhone(string $phone = '+255765000123'): \App\Models\Order
    {
        $buyer = User::factory()->create(['phone' => '+255711000111', 'email' => 'buyer@example.com']);
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
            ->json('data.id');

        return \App\Models\Order::query()->findOrFail($id);
    }

    public function test_config_tells_the_app_to_use_the_new_flow_and_how_the_fee_is_paid(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/delivery/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.min_app_version', '1.1.0')
            ->assertJsonPath('data.unpaid_timeout_minutes', 20)
            ->assertJsonPath('data.payment.mode', 'online')
            ->assertJsonPath('data.payment.online_available', true)
            ->assertJsonPath('data.payment.covers', ['delivery_fee'])
            ->assertJsonPath('data.payment.goods_paid_to', 'shop')
            ->assertJsonStructure(['data' => ['max_distance_km', 'payment' => ['channels', 'sandbox'], 'tracking' => ['poll_seconds', 'realtime']]]);

        config(['sokoni.delivery_fee_cash_enabled' => true]);
        $this->actingAs(User::factory()->create())->getJson('/api/delivery/config')->assertJsonPath('data.payment.mode', 'cash');
    }

    public function test_config_404s_when_delivery_is_off_so_the_app_keeps_the_1_0_checkout(): void
    {
        config(['delivery.enabled' => false]);
        $this->actingAs(User::factory()->create())->getJson('/api/delivery/config')->assertNotFound();
    }

    public function test_quote_returns_the_server_fee_for_the_checkout_estimate(): void
    {
        $shop = $this->shop();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/delivery/quote?'.http_build_query(['seller_id' => $shop->id, 'lat' => self::DROP_LAT, 'lng' => self::DROP_LNG]))
            ->assertOk()
            ->assertJsonPath('data.estimate', true)
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonStructure(['data' => ['distance_km', 'distance_source', 'base_fee', 'per_km_fee', 'fee']]);
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
            ->assertJsonPath('data.fulfilment', null)
            ->assertJsonPath('data.delivery_fee', 3000)
            ->assertJsonPath('data.total', 23000)
            ->assertJsonMissingPath('data.delivery_phone')
            ->json('data.id');

        $this->actingAs($shop->user)->getJson("/api/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.buyer.phone', '+255711000999');
    }

    public function test_the_seller_never_gets_the_buyers_contact_details_on_a_dispatch_order(): void
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

        // And the seller's delivery view has no PIN, fee, payment or drop-off.
        $job = $this->acceptAsSeller($order);
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST');
        $seller->refresh();
        $view = $this->actingAs($seller)->getJson("/api/delivery/orders/{$order->id}")->assertOk()->json('data');
        foreach (['delivery_pin', 'fee', 'payment', 'dropoff', 'amount_due', 'driver_location'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $view, "seller view leaks {$hidden}");
        }
    }

    public function test_the_driver_calls_the_phone_given_at_checkout_and_only_while_active(): void
    {
        $order = $this->dispatchOrderWithPhone('+255765000123');
        $job = $this->acceptAsSeller($order);
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST');
        $driver = $this->driverAt(1);
        $this->artisan('delivery:dispatch-tick');

        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk()
            ->assertJsonPath('data.customer.phone', '+255765000123');

        $this->actingAs($order->buyer)->getJson("/api/delivery/orders/{$order->id}")
            ->assertJsonPath('data.dropoff.phone', '+255765000123');
    }

    public function test_buyer_view_has_every_field_the_1_1_tracking_screen_reads(): void
    {
        $job = $this->acceptAsSeller($this->dispatchOrderWithPhone());

        $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.amount_due', $job->fee)
            ->assertJsonPath('data.payment.goods_total', 20000)
            ->assertJsonPath('data.payment.delivery_fee', $job->fee)
            ->assertJsonPath('data.payment.total', 20000 + $job->fee)
            ->assertJsonPath('data.payment.attempt', null)
            ->assertJsonPath('data.actions.can_pay', true)
            ->assertJsonPath('data.delivery_pin', null)
            ->assertJsonStructure(['data' => [
                'id', 'order_id', 'order_code', 'status', 'is_active', 'distance_km', 'currency', 'fee',
                'payment_status', 'amount_due', 'pay_cash_to_driver', 'delivery_pin', 'pin_locked', 'dispatch_wave',
                'pickup' => ['shop_name', 'lat', 'lng'],
                'dropoff' => ['lat', 'lng', 'address', 'phone'],
                'payment' => ['mode', 'status', 'amount_due', 'covers', 'goods_paid_to', 'goods_total', 'delivery_fee',
                    'total', 'deadline', 'online_available', 'sandbox', 'channels', 'attempt', 'receipt'],
                'driver', 'driver_location', 'tracking', 'refund',
                'actions' => ['can_pay', 'can_cancel', 'can_retry', 'can_switch_to_pickup', 'can_rate'],
                'timeline', 'cancelled_by', 'cancel_reason', 'failure_reason', 'created_at',
            ]]);

        // The deadline is the unpaid timeout counted from acceptance.
        $deadline = $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")->json('data.payment.deadline');
        $this->assertEquals($job->created_at->addMinutes(20)->toIso8601String(), \Illuminate\Support\Carbon::parse($deadline)->toIso8601String());
    }

    public function test_every_status_change_lands_in_the_customer_inbox_with_the_order_id(): void
    {
        $this->app->instance(DeliveryNotifier::class, new LogDeliveryNotifier);
        $order = $this->dispatchOrderWithPhone();
        $job = $this->acceptAsSeller($order);
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST');

        $buyerInbox = $order->buyer->appNotifications()->get();
        $this->assertTrue($buyerInbox->contains(fn ($n) => ($n->data['status'] ?? null) === 'pending_payment'));
        $this->assertTrue($buyerInbox->contains(fn ($n) => ($n->data['status'] ?? null) === 'pending_driver'));
        $this->assertTrue($buyerInbox->every(fn ($n) => ($n->data['order_id'] ?? null) === $order->id || ! isset($n->data['type'])));

        $sellerInbox = $order->seller->user->appNotifications()->get();
        $this->assertTrue($sellerInbox->contains(fn ($n) => ($n->data['type'] ?? null) === 'delivery_status' && ($n->data['status'] ?? null) === 'pending_driver'));

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

<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\FeeCalculator;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAndJobCreationTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
        ])->assertCreated();

        $response->assertJsonPath('data.delivery_fee', 3000);
        $response->assertJsonPath('data.total', 13000);
        $response->assertJsonPath('data.fulfilment', null);
    }

    public function test_flag_on_but_sokoni_1_0_request_is_byte_for_byte_the_old_flow(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop()->id, 'price' => 10000]);

        $id = $this->legacyDeliveryCheckout(User::factory()->create(), $product)
            ->assertCreated()
            ->assertJsonPath('data.delivery_fee', 3000)
            ->assertJsonPath('data.total', 13000)
            ->json('data.id');

        $order = Order::query()->findOrFail($id);
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$id}/status", ['status' => 'accepted'])->assertOk();
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$id}/status", ['status' => 'ready'])->assertOk();
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$id}/status", ['status' => 'completed'])->assertOk();

        $this->assertSame(0, DeliveryJob::query()->count());
        $this->assertSame([], $this->pushes->sent);
    }

    public function test_dispatch_checkout_keeps_the_fee_out_of_the_sellers_total(): void
    {
        $order = $this->placeDispatchOrder();

        $this->assertSame('dispatch', $order->fulfilment);
        $this->assertSame(0, (int) $order->delivery_fee);
        $this->assertSame(40000, (int) $order->total);
        $this->assertNull($order->deliveryJob, 'No job until the seller accepts.');
    }

    public function test_dispatch_checkout_needs_a_map_pin(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop()->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, ['fulfilment' => 'dispatch'])
            ->assertStatus(422)->assertJsonValidationErrors('delivery_lat');
    }

    public function test_dispatch_checkout_rejects_drop_offs_beyond_the_max_distance(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shop()->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, [
            'fulfilment' => 'dispatch', 'delivery_lat' => self::SHOP_LAT + 0.12, 'delivery_lng' => self::SHOP_LNG,
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_lat');
    }

    public function test_dispatch_checkout_rejects_shops_without_a_location(): void
    {
        $shop = SellerProfile::factory()->verified()->create(['lat' => null, 'lng' => null]);
        $product = Product::factory()->create(['seller_id' => $shop->id]);

        $this->legacyDeliveryCheckout(User::factory()->create(), $product, [
            'fulfilment' => 'dispatch', 'delivery_lat' => self::DROP_LAT, 'delivery_lng' => self::DROP_LNG,
        ])->assertStatus(422)->assertJsonValidationErrors('fulfilment');
    }

    public function test_seller_acceptance_creates_a_priced_job_awaiting_payment(): void
    {
        $nearby = $this->driverAt(1);
        $order = $this->placeDispatchOrder();
        $job = $this->acceptAsSeller($order);

        $expected = app(FeeCalculator::class)->quote(self::SHOP_LAT, self::SHOP_LNG, self::DROP_LAT, self::DROP_LNG);

        $this->assertSame('pending_payment', $job->status);
        $this->assertSame('unpaid', $job->payment_status);
        $this->assertSame($expected->fee, $job->fee);
        $this->assertSame($expected->driverEarnings, $job->driver_earnings);
        $this->assertSame($job->fee, $job->commission + $job->driver_earnings);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $job->delivery_pin);
        $this->assertSame(self::SHOP_LAT, $job->pickup_lat);
        $this->assertSame('Mtaa wa Uhuru 12', $job->dropoff_address);

        // Buyer is asked to pay; no driver hears anything before payment.
        $this->assertSame('sokoni', $this->pushes->to($order->buyer)->last()['app']);
        $this->assertCount(0, $this->pushes->to($nearby->user));
        $this->assertSame(0, $job->offers()->count());
    }

    public function test_verified_payment_starts_dispatch_and_is_idempotent(): void
    {
        $nearby = $this->driverAt(1);
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $service = app(DeliveryJobService::class);

        $service->markFeePaid($job, 'REF-1');
        $service->markFeePaid($job, 'REF-1');

        $job->refresh();
        $this->assertSame('pending_driver', $job->status);
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame(1, $job->dispatch_wave);
        $this->assertSame(1, $job->events()->where('to_status', 'pending_driver')->count());
        $this->assertCount(1, $this->pushes->ofType('delivery_offer')->where('user_id', $nearby->user_id));
    }

    public function test_cash_fee_setting_skips_online_payment(): void
    {
        Settings::set('delivery_fee_cash_enabled', '1');
        $this->driverAt(1);

        $job = $this->acceptAsSeller($this->placeDispatchOrder());

        $this->assertSame('pending_driver', $job->status);
        $this->assertSame('cash', $job->payment_status);
        $this->assertSame(1, $job->offers()->count());
    }

    public function test_seller_cannot_complete_a_dispatch_order_while_the_delivery_is_open(): void
    {
        $job = $this->paidJob();
        $order = $job->order;

        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'ready'])->assertOk();
        $this->actingAs($order->seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'completed'])->assertStatus(422);
    }

    public function test_order_resource_exposes_fulfilment_additively(): void
    {
        $order = $this->placeDispatchOrder();

        $this->actingAs($order->buyer)->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.fulfilment', 'dispatch')
            ->assertJsonPath('data.code', $order->code);
    }
}

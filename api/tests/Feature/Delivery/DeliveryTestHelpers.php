<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryNotifier;
use Illuminate\Support\Sleep;
use Tests\Feature\Delivery\Bolt\FakesBolt;

/**
 * Geography used throughout (Dar es Salaam):
 *   shop       Kariakoo         -6.8190, 39.2760
 *   drop-off   ~3.3 km north    -6.7900, 39.2800
 *
 * Bolt is faked (FakesBolt): the cheapest bookable category is
 * "bolt_send", TSh 5,200 - 6,400, so every accepted delivery costs the
 * buyer TSh 6,400 unless a test changes $this->boltEstimates.
 */
trait DeliveryTestHelpers
{
    use FakesBolt;

    protected const SHOP_LAT = -6.8190;

    protected const SHOP_LNG = 39.2760;

    protected const DROP_LAT = -6.7900;

    protected const DROP_LNG = 39.2800;

    protected const FEE = 6400;

    protected RecordingDeliveryNotifier $pushes;

    protected function setUpDelivery(): void
    {
        Sleep::fake(); // Bolt retry backoffs never really wait in tests
        $this->enableBolt();
        $this->pushes = new RecordingDeliveryNotifier;
        $this->app->instance(DeliveryNotifier::class, $this->pushes);
    }

    protected function shop(array $attributes = []): SellerProfile
    {
        $owner = User::factory()->create(['phone' => '+255754'.random_int(100000, 999999)]);

        return SellerProfile::factory()->verified()->create([
            'user_id' => $owner->id,
            'lat' => self::SHOP_LAT,
            'lng' => self::SHOP_LNG,
            'shop_name' => 'Duka la Juma',
            ...$attributes,
        ]);
    }

    /** A Bolt delivery order placed through the real checkout endpoint. */
    protected function placeDispatchOrder(?User $buyer = null, ?SellerProfile $shop = null, array $extra = []): Order
    {
        $buyer ??= User::factory()->create(['phone' => '+255711'.random_int(100000, 999999), 'name' => 'Asha Mteja']);
        $shop ??= $this->shop();
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 20000]);

        $id = $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'delivery_method' => 'delivery',
            'address' => 'Mtaa wa Uhuru 12',
            'delivery_lat' => self::DROP_LAT,
            'delivery_lng' => self::DROP_LNG,
            'payment_method' => 'cash_on_delivery',
            'fulfilment' => 'dispatch',
            ...$extra,
        ])->assertCreated()->json('data.id');

        return Order::query()->findOrFail($id);
    }

    /** Seller accepts through the existing endpoint, which creates and prices the job. */
    protected function acceptAsSeller(Order $order): DeliveryJob
    {
        $this->actingAs($order->seller->user)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertOk();

        return $order->fresh()->deliveryJob;
    }

    /** Seller marks the order ready for pickup through the existing endpoint. */
    protected function markReady(Order $order): DeliveryJob
    {
        $this->actingAs($order->seller->user)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertOk();

        return $order->fresh()->deliveryJob;
    }

    /** Order placed, accepted and fee paid: the job waits for the shop to mark it ready. */
    protected function paidJob(?User $buyer = null, ?SellerProfile $shop = null): DeliveryJob
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder($buyer, $shop));
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST-REF');

        return $job->fresh();
    }

    /** Paid and ready: the Bolt ride is booked (pending_driver, Bolt SEARCHING). */
    protected function bookedJob(?User $buyer = null, ?SellerProfile $shop = null): DeliveryJob
    {
        $job = $this->paidJob($buyer, $shop);

        return $this->markReady($job->order);
    }

    /** Moves the job's Bolt ride and runs the 10-second tracker once. */
    protected function boltMoves(DeliveryJob $job, string $state): DeliveryJob
    {
        $this->boltState((string) $job->fresh()->provider_ref, $state);
        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();

        return $job->fresh();
    }
}

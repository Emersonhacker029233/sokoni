<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryNotifier;

/**
 * Geography used throughout (Dar es Salaam):
 *   shop       Kariakoo         -6.8190, 39.2760
 *   drop-off   ~3.3 km north    -6.7900, 39.2800
 *   driverAt(km) places a driver that many km due north of the shop.
 */
trait DeliveryTestHelpers
{
    protected const SHOP_LAT = -6.8190;

    protected const SHOP_LNG = 39.2760;

    protected const DROP_LAT = -6.7900;

    protected const DROP_LNG = 39.2800;

    protected RecordingDeliveryNotifier $pushes;

    protected function setUpDelivery(): void
    {
        config(['delivery.enabled' => true]);
        $this->pushes = new RecordingDeliveryNotifier;
        $this->app->instance(DeliveryNotifier::class, $this->pushes);
    }

    protected function shop(): SellerProfile
    {
        return SellerProfile::factory()->verified()->create(['lat' => self::SHOP_LAT, 'lng' => self::SHOP_LNG, 'shop_name' => 'Duka la Juma']);
    }

    /** A dispatch order placed through the real checkout endpoint. */
    protected function placeDispatchOrder(?User $buyer = null, ?SellerProfile $shop = null): Order
    {
        $buyer ??= User::factory()->create(['phone' => '+255711000111']);
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
        ])->assertCreated()->json('data.id');

        return Order::query()->findOrFail($id);
    }

    /** Seller accepts through the existing endpoint, which creates the job. */
    protected function acceptAsSeller(Order $order): DeliveryJob
    {
        $this->actingAs($order->seller->user)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertOk();

        return $order->fresh()->deliveryJob;
    }

    /** Order placed, accepted and fee paid: the job is now pending_driver with wave 1 offered. */
    protected function paidJob(?User $buyer = null): DeliveryJob
    {
        $job = $this->acceptAsSeller($this->placeDispatchOrder($buyer));
        app(DeliveryJobService::class)->markFeePaid($job, 'TEST-REF');

        return $job->fresh();
    }

    protected function driverAt(float $kmNorth): DriverProfile
    {
        return DriverProfile::factory()->approvedOnline(self::SHOP_LAT + $kmNorth / 111.2, self::SHOP_LNG)->create();
    }

    protected function asDriver(DriverProfile $driver): static
    {
        return $this->actingAs($driver->user->fresh());
    }

    /** Drive an assigned job to `arrived` through the real endpoints. */
    protected function driveToArrived(DeliveryJob $job, DriverProfile $driver): DeliveryJob
    {
        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/status", ['status' => 'arrived_at_shop'])->assertOk();
        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/status", [
            'status' => 'picked_up', 'pickup_code' => $job->fresh()->pickupCode(),
        ])->assertOk();
        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/status", ['status' => 'on_the_way'])->assertOk();
        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/status", ['status' => 'arrived'])->assertOk();

        return $job->fresh();
    }
}

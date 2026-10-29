<?php

namespace App\Http\Resources\Delivery;

use App\Models\DeliveryJob;
use App\Support\DeliverySettings;
use App\Support\PrivateFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One delivery job, shaped for whoever is looking at it.
 *
 * Privacy rules (client-approved 2026-10-29):
 *  - phone numbers only while the job is active (assigned through arrived),
 *  - live location only for the buyer, only while active,
 *  - the delivery PIN only for the buyer,
 *  - the seller never sees the PIN or the driver's location.
 *
 * @mixin DeliveryJob
 */
class DeliveryJobResource extends JsonResource
{
    public const BUYER = 'buyer';

    public const SELLER = 'seller';

    public const DRIVER = 'driver';

    private string $viewer = self::BUYER;

    public static function for(DeliveryJob $job, string $viewer): self
    {
        $resource = new self($job);
        $resource->viewer = $viewer;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['order.seller.user', 'order.buyer', 'order.items', 'driver.user', 'vehicle', 'events', 'ratings']);

        $base = [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_code' => $this->order->code,
            'status' => $this->status,
            'is_active' => $this->isDriverActive(),
            'distance_km' => $this->distance_km,
            'currency' => 'TZS',
            'timeline' => $this->timeline(),
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];

        return match ($this->viewer) {
            self::DRIVER => [...$base, ...$this->driverView()],
            self::SELLER => [...$base, ...$this->sellerView()],
            default => [...$base, ...$this->buyerView()],
        };
    }

    private function buyerView(): array
    {
        $active = $this->isDriverActive();
        $hasDriver = $this->driver !== null && in_array($this->status, [...DeliveryJob::DRIVER_ACTIVE_STATUSES, 'delivered'], true);
        $reassignable = in_array($this->status, ['no_driver_found', 'failed'], true) && $this->failure_reason !== 'customer_unreachable';

        return [
            'fee' => $this->fee,
            'payment_status' => $this->payment_status,
            'amount_due' => $this->payment_status === 'unpaid' && $this->status === 'pending_payment' ? $this->fee : 0,
            'pay_cash_to_driver' => $this->payment_status === 'cash',
            'delivery_pin' => ! $this->isTerminal() && $this->status !== 'pending_payment' ? $this->delivery_pin : null,
            'pin_locked' => $this->pin_locked_at !== null,
            'dispatch_wave' => $this->status === 'pending_driver' ? $this->dispatch_wave : null,
            'pickup' => [
                'shop_name' => $this->order->seller?->shop_name,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
            ],
            'dropoff' => ['lat' => $this->dropoff_lat, 'lng' => $this->dropoff_lng, 'address' => $this->dropoff_address],
            'driver' => $hasDriver ? [
                'name' => $this->driver->user->name,
                'photo_url' => PrivateFileUrl::for($this->driver->photo),
                'rating_avg' => $this->driver->rating_avg,
                'rating_count' => $this->driver->rating_count,
                'phone' => $active ? $this->driver->user->phone : null,
                'vehicle' => $this->vehicleArray(),
            ] : null,
            'driver_location' => $active && $this->driver?->last_lat !== null ? [
                'lat' => $this->driver->last_lat,
                'lng' => $this->driver->last_lng,
                'at' => $this->driver->last_located_at,
            ] : null,
            'tracking' => $active ? ['channel' => "tracking/{$this->id}"] : null,
            'refund' => $this->refund_amount ? ['amount' => $this->refund_amount, 'status' => $this->refund_status] : null,
            'actions' => [
                'can_pay' => $this->status === 'pending_payment' && $this->payment_status === 'unpaid',
                'can_cancel' => ! $this->isTerminal() && ! $this->isInTransit() && ! ($this->status === 'failed' && ! $reassignable),
                'can_retry' => $reassignable,
                'can_switch_to_pickup' => in_array($this->status, ['pending_payment', 'pending_driver', 'no_driver_found'], true) || ($this->status === 'failed' && $reassignable),
                'can_rate' => $this->status === 'delivered' && ! $this->ratings->contains('direction', 'buyer_to_driver'),
            ],
        ];
    }

    private function sellerView(): array
    {
        $atShopPhase = in_array($this->status, ['driver_assigned', 'arrived_at_shop'], true);

        return [
            'driver' => $this->driver !== null && $this->isDriverActive() ? [
                'name' => $this->driver->user->name,
                'photo_url' => PrivateFileUrl::for($this->driver->photo),
                'phone' => $atShopPhase ? $this->driver->user->phone : null,
                'vehicle' => $this->vehicleArray(),
            ] : null,
            // The seller hands over only after the driver types these 4 characters.
            'pickup_code' => $atShopPhase ? $this->pickupCode() : null,
        ];
    }

    private function driverView(): array
    {
        $order = $this->order;
        $active = $this->isDriverActive();
        $beforePickup = in_array($this->status, ['driver_assigned', 'arrived_at_shop'], true);
        $waitMinutes = (int) config('delivery.unreachable_wait_minutes');

        return [
            'fee' => $this->fee,
            'commission' => $this->commission,
            'driver_earnings' => $this->driver_earnings,
            'earned_amount' => $this->earned_amount,
            'payment_status' => $this->payment_status,
            'collect_cash' => $this->payment_status === 'cash' ? $this->fee : 0,
            'shop' => [
                'name' => $order->seller?->shop_name,
                'address' => $this->pickup_address,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
                'phone' => $beforePickup ? ($order->seller?->user?->phone ?? $order->seller?->whatsapp) : null,
            ],
            'customer' => [
                'name' => $order->buyer?->name,
                'phone' => $active ? $order->buyer?->phone : null,
                'address' => $this->dropoff_address,
                'lat' => $this->dropoff_lat,
                'lng' => $this->dropoff_lng,
            ],
            'items' => $order->items->map(fn ($i) => ['title' => $i->title_snapshot, 'qty' => $i->qty])->values(),
            'notes' => $order->notes,
            'call_attempts' => $this->call_attempts,
            'pin_attempts_left' => max(0, (int) config('delivery.pin_max_attempts') - $this->pin_attempts),
            'pin_locked' => $this->pin_locked_at !== null,
            'proof_photo_required' => DeliverySettings::proofPhotoRequired(),
            'unreachable_available_at' => $this->status === 'arrived' && $this->arrived_at ? $this->arrived_at->copy()->addMinutes($waitMinutes) : null,
            'actions' => [
                'next' => array_values(array_intersect(
                    DeliveryJob::TRANSITIONS[$this->status] ?? [],
                    ['arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered', 'failed'],
                )),
                'can_cancel' => $beforePickup,
                'can_rate' => $this->status === 'delivered' && ! $this->ratings->contains('direction', 'driver_to_buyer'),
            ],
        ];
    }

    private function vehicleArray(): ?array
    {
        return $this->vehicle ? [
            'type' => $this->vehicle->type,
            'plate_number' => $this->vehicle->plate_number,
            'make' => $this->vehicle->make,
            'colour' => $this->vehicle->colour,
        ] : null;
    }

    private function timeline(): array
    {
        return $this->events
            ->where('type', 'status')
            ->map(fn ($e) => ['status' => $e->to_status, 'at' => $e->created_at, 'by' => $e->actor_role])
            ->values()
            ->all();
    }
}

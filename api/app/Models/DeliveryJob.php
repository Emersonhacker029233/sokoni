<?php

namespace App\Models;

use App\Services\Delivery\Bolt\BoltStates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Bolt delivery for one order (docs/delivery/BOLT.md).
 *
 * Nothing on this model is mass-assignable. Jobs are created by
 * DeliveryJobService and every status change goes through
 * DeliveryStateMachine, which also writes the shared timeline and pushes.
 *
 * Statuses, in the order a delivery normally takes them:
 *   pending_quote    shop accepted; asking Bolt for the price
 *   pending_payment  priced; the buyer pays the delivery fee in the app
 *   awaiting_ready   paid; waiting for the shop to mark the order ready
 *   pending_driver   Bolt ride booked, Bolt is searching (SEARCHING)
 *   driver_assigned  driver on the way to the shop
 *   arrived_at_shop  driver at the shop
 *   on_the_way       parcel in the car, heading to the customer
 *   delivered        Bolt says COMPLETED; the order is completed
 * and the problem states no_driver_found, failed and cancelled.
 * `picked_up` and `arrived` belong to the retired own-driver flow and only
 * appear on old rows.
 */
class DeliveryJob extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    public const PROVIDER_BOLT = 'bolt';

    public const STATUSES = [
        'pending_quote', 'pending_payment', 'awaiting_ready', 'pending_driver', 'no_driver_found',
        'driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered', 'cancelled', 'failed',
    ];

    /** A Bolt driver is on the job (assigned through en route to the customer). */
    public const DRIVER_ACTIVE_STATUSES = ['driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived'];

    /** A booked Bolt ride is running: its state is polled every 10 s. */
    public const RIDE_ACTIVE_STATUSES = ['pending_driver', 'driver_assigned', 'arrived_at_shop', 'on_the_way'];

    /** Goods have left the shop: no cancelling, only deliver or fail. */
    public const IN_TRANSIT_STATUSES = ['picked_up', 'on_the_way', 'arrived'];

    public const TERMINAL_STATUSES = ['delivered', 'cancelled'];

    /**
     * Every legal edge. Bolt can skip states between two polls (SEARCHING
     * straight to COMPLETED in the sandbox), so a booked ride may move to
     * any later status; it can also fall back from driver_assigned to
     * pending_driver when a driver drops the ride.
     */
    public const TRANSITIONS = [
        'pending_quote' => ['pending_payment', 'no_driver_found', 'cancelled'],
        'pending_payment' => ['awaiting_ready', 'cancelled'],
        'awaiting_ready' => ['pending_driver', 'no_driver_found', 'cancelled'],
        'pending_driver' => ['driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered', 'no_driver_found', 'failed', 'cancelled'],
        'driver_assigned' => ['pending_driver', 'arrived_at_shop', 'on_the_way', 'delivered', 'no_driver_found', 'failed', 'cancelled'],
        'arrived_at_shop' => ['pending_driver', 'on_the_way', 'delivered', 'failed', 'cancelled'],
        // `cancelled` here is admin-only (DeliveryJobService::cancel refuses anyone else once the parcel left the shop).
        'on_the_way' => ['delivered', 'failed', 'cancelled'],
        'no_driver_found' => ['pending_quote', 'awaiting_ready', 'cancelled'],
        'failed' => ['pending_quote', 'awaiting_ready', 'cancelled'],
        // Own-driver states, only on rows from before Bolt.
        'picked_up' => ['on_the_way', 'delivered', 'failed'],
        'arrived' => ['delivered', 'failed'],
        'delivered' => [],
        'cancelled' => [],
    ];

    protected function casts(): array
    {
        return [
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'distance_km' => 'float',
            'commission_pct' => 'float',
            'estimate_min' => 'float',
            'estimate_max' => 'float',
            'final_cost' => 'float',
            'cost_difference' => 'float',
            'provider_driver' => 'array',
            'receipt' => 'array',
            'delivery_pin' => 'encrypted',
            'paid_at' => 'datetime',
            'pin_locked_at' => 'datetime',
            'wave_started_at' => 'datetime',
            'booked_at' => 'datetime',
            'last_polled_at' => 'datetime',
            'receipt_checked_at' => 'datetime',
            'next_booking_at' => 'datetime',
            'pending_payment_at' => 'datetime',
            'awaiting_ready_at' => 'datetime',
            'pending_driver_at' => 'datetime',
            'driver_assigned_at' => 'datetime',
            'arrived_at_shop_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'on_the_way_at' => 'datetime',
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'no_driver_found_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryJobEvent::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isDriverActive(): bool
    {
        return in_array($this->status, self::DRIVER_ACTIVE_STATUSES, true);
    }

    public function isRideActive(): bool
    {
        return $this->provider_ref !== null && in_array($this->status, self::RIDE_ACTIVE_STATUSES, true);
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, self::IN_TRANSIT_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isBolt(): bool
    {
        return $this->provider === self::PROVIDER_BOLT;
    }

    public function feeSettled(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * The goods are still at the shop, so booking again, switching to
     * pickup or cancelling are all possible. A failure after pickup
     * (Bolt cancelled with the parcel in the car) is for support.
     */
    public function parcelAtShop(): bool
    {
        if ($this->isInTransit() || $this->picked_up_at !== null) {
            return false;
        }

        return $this->status !== 'failed' || BoltStates::retryable($this->failure_reason);
    }

    /** @return array{lat: float, lng: float} */
    public function pickupPoint(): array
    {
        return ['lat' => (float) $this->pickup_lat, 'lng' => (float) $this->pickup_lng];
    }

    /** @return array{lat: float, lng: float} */
    public function dropoffPoint(): array
    {
        return ['lat' => (float) $this->dropoff_lat, 'lng' => (float) $this->dropoff_lng];
    }

    /** When the buyer's payment window opened (older rows: when the job was created). */
    public function paymentWindowStart(): Carbon
    {
        return $this->pending_payment_at ?? $this->created_at;
    }
}

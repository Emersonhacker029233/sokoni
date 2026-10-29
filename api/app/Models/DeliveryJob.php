<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nothing on this model is mass-assignable. Jobs are created by
 * DeliveryJobService and every status change goes through
 * DeliveryStateMachine (which also writes the event timeline and pushes).
 */
class DeliveryJob extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    public const STATUSES = [
        'pending_payment', 'pending_driver', 'no_driver_found', 'driver_assigned', 'arrived_at_shop',
        'picked_up', 'on_the_way', 'arrived', 'delivered', 'cancelled', 'failed',
    ];

    /** A driver is "busy" in these. */
    public const DRIVER_ACTIVE_STATUSES = ['driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived'];

    /** Goods are with the driver: no cancelling, only deliver or fail. */
    public const IN_TRANSIT_STATUSES = ['picked_up', 'on_the_way', 'arrived'];

    public const TERMINAL_STATUSES = ['delivered', 'cancelled'];

    /**
     * Every legal edge. `failed` is terminal for the driver, but the buyer
     * may retry a failed job unless the failure was customer_unreachable
     * (enforced in DeliveryJobService::retry).
     */
    public const TRANSITIONS = [
        'pending_payment' => ['pending_driver', 'cancelled'],
        'pending_driver' => ['driver_assigned', 'no_driver_found', 'cancelled'],
        'no_driver_found' => ['pending_driver', 'cancelled'],
        'driver_assigned' => ['arrived_at_shop', 'pending_driver', 'cancelled'],
        'arrived_at_shop' => ['picked_up', 'pending_driver', 'cancelled'],
        'picked_up' => ['on_the_way', 'arrived', 'failed'],
        'on_the_way' => ['arrived', 'failed'],
        'arrived' => ['delivered', 'failed'],
        'failed' => ['pending_driver', 'cancelled'],
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
            'delivery_pin' => 'encrypted',
            'paid_at' => 'datetime',
            'pin_locked_at' => 'datetime',
            'wave_started_at' => 'datetime',
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryJobEvent::class)->orderBy('id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DeliveryOffer::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(DeliveryRating::class);
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

    public function isInTransit(): bool
    {
        return in_array($this->status, self::IN_TRANSIT_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /** Whether the fee has been settled (online, or agreed cash collection). */
    public function feeSettled(): bool
    {
        return in_array($this->payment_status, ['paid', 'cash'], true);
    }

    /**
     * Pickup confirmation: the last 4 characters of the existing order code
     * (SKxxxxxxxx), which sellers already see in Sokoni 1.0.
     */
    public function pickupCode(): string
    {
        return strtoupper(substr((string) $this->order->code, -4));
    }
}

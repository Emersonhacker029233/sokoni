<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A buyer's request to return a Bolt-delivered order (docs/delivery/RETURNS.md),
 * and the Bolt ride that brings the item back (customer -> shop).
 *
 *   requested ─┬─ accepted ── return_in_transit ── received ── refunded
 *              │      │              └──────────────────────────┘
 *              └─ rejected ── disputed ── (admin) accepted | closed
 *   any open state ── closed (withdrawn by the buyer, or closed by an admin)
 *
 * The shop has 48 hours to accept or reject; after that the request is
 * escalated to Sokoni support (escalated_at). Nothing is mass-assignable:
 * ReturnService is the only writer.
 */
class ReturnRequest extends Model
{
    protected $guarded = ['*'];

    public const REASONS = ['changed_mind', 'wrong_item', 'damaged', 'not_as_described'];

    public const STATUSES = ['requested', 'accepted', 'rejected', 'disputed', 'return_in_transit', 'received', 'refunded', 'closed'];

    public const TRANSITIONS = [
        'requested' => ['accepted', 'rejected', 'closed'],
        'rejected' => ['disputed', 'accepted', 'closed'],
        'disputed' => ['accepted', 'closed'],
        // `refunded` straight from accepted: the item came back some other way (e.g. Bolt can't reach the buyer).
        'accepted' => ['return_in_transit', 'refunded', 'closed'],
        // Back to `accepted` when the ride fails before pickup, so the buyer can book again.
        'return_in_transit' => ['accepted', 'received', 'refunded', 'closed'],
        'received' => ['refunded', 'closed'],
        'refunded' => ['closed'],
        'closed' => [],
    ];

    /** Hours the shop has to accept or reject before Sokoni support takes over. */
    public const SELLER_RESPONSE_HOURS = 48;

    /** ride_status values while a Bolt ride is running. */
    public const RIDE_ACTIVE = ['pending_driver', 'driver_assigned', 'at_pickup', 'on_the_way'];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'provider_driver' => 'array',
            'receipt' => 'array',
            'estimate_min' => 'float',
            'estimate_max' => 'float',
            'final_cost' => 'float',
            'cost_difference' => 'float',
            'decided_at' => 'datetime',
            'escalated_at' => 'datetime',
            'disputed_at' => 'datetime',
            'paid_at' => 'datetime',
            'booked_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'last_polled_at' => 'datetime',
            'receipt_checked_at' => 'datetime',
            'received_at' => 'datetime',
            'refunded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'shop_id')->withTrashed();
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReturnRequestPhoto::class)->orderBy('sort');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Changed mind: the buyer pays the ride back. Anything the shop got wrong: the shop pays. */
    public static function payerFor(string $reason): string
    {
        return $reason === 'changed_mind' ? 'buyer' : 'seller';
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['refunded', 'closed'], true);
    }

    public function isRideActive(): bool
    {
        return $this->provider_ref !== null && in_array($this->ride_status, self::RIDE_ACTIVE, true);
    }

    /** The ride can be booked once paid (buyer pays) or straight away (shop pays). */
    public function feeSettled(): bool
    {
        return in_array($this->fee_status, ['paid', 'owed_by_seller', 'waived'], true);
    }

    /**
     * Sokoni support must act: the shop didn't answer in time, the buyer
     * disputed, the shop blocked the buyer, or Bolt's ride back ended with
     * the item in the car.
     */
    public function needsAdmin(): bool
    {
        return $this->status === 'disputed'
            || ($this->status === 'requested' && $this->escalated_at !== null)
            || $this->rideFailedInTransit();
    }

    /** The ride back failed after the driver collected the item: a person must recover it. */
    public function rideFailedInTransit(): bool
    {
        return $this->status === 'return_in_transit' && $this->picked_up_at !== null
            && in_array($this->ride_status, ['failed', 'no_driver_found'], true);
    }

    /** A buyer-paid ride back that never carried the item, still to be paid back by an admin. */
    public function rideFeeRefundPending(): bool
    {
        return $this->fee_status === 'refund_pending';
    }

    public function sellerDeadline(): Carbon
    {
        return $this->created_at->copy()->addHours(self::SELLER_RESPONSE_HOURS);
    }

    /** The return ride starts where the parcel was delivered. */
    public function pickupPoint(): array
    {
        return ['lat' => (float) $this->order->delivery_lat, 'lng' => (float) $this->order->delivery_lng];
    }

    public function dropoffPoint(): array
    {
        return ['lat' => (float) $this->shop->lat, 'lng' => (float) $this->shop->lng];
    }
}

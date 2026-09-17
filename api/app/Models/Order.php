<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'buyer_id', 'seller_id', 'subtotal', 'delivery_fee', 'total',
    'delivery_method', 'address', 'delivery_lat', 'delivery_lng', 'notes',
    'payment_method',
])]
class Order extends Model
{
    use HasFactory;

    /** Allowed forward transitions; cancelled is reachable from pending/accepted/ready. */
    public const TRANSITIONS = [
        'pending' => ['accepted', 'cancelled'],
        'accepted' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= static::generateUniqueCode();
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'SK'.strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    protected function casts(): array
    {
        return [
            'delivery_lat' => 'decimal:7',
            'delivery_lng' => 'decimal:7',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Language audit (client feedback): the website used to render this
     * raw ('pending'/'accepted'/...) via `ucfirst()`, which is always
     * English regardless of the visitor's chosen language — exactly the
     * "mixture of two languages" the Kiswahili-default audit called out.
     * Static so the timeline's own status strings (below) can share it
     * without needing a full Order instance.
     */
    public static function labelForStatus(string $status): string
    {
        return __('site.order_status_'.$status);
    }

    public function statusLabel(): string
    {
        return self::labelForStatus($this->status);
    }

    /** Same reasoning as statusLabel() — delivery_method was also shown raw via ucfirst(). */
    public function deliveryMethodLabel(): string
    {
        return __('site.order_delivery_'.$this->delivery_method);
    }

    /** Ordered list of [status, timestamp] pairs both parties see as the order's timeline. */
    public function timeline(): array
    {
        $steps = [
            ['status' => 'pending', 'at' => $this->created_at],
            ['status' => 'accepted', 'at' => $this->accepted_at],
            ['status' => 'ready', 'at' => $this->ready_at],
            ['status' => 'completed', 'at' => $this->completed_at],
        ];

        if ($this->status === 'cancelled') {
            $steps[] = ['status' => 'cancelled', 'at' => $this->cancelled_at];
        }

        return array_values(array_filter($steps, fn ($step) => $step['at'] !== null));
    }
}

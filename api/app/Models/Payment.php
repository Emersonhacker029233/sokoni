<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A delivery-fee collection attempt. Status changes only via a verified webhook (phase 5). */
class Payment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['msisdn'];

    protected function casts(): array
    {
        return [
            'msisdn' => 'encrypted',
            'meta' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryJob(): BelongsTo
    {
        return $this->belongsTo(DeliveryJob::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A delivery-fee or return-ride-fee collection attempt. Only the server, asking the gateway, marks it paid. */
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

    /** A buyer-paid Bolt ride back for a "changed my mind" return (purpose return_fee). */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}

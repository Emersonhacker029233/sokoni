<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Unique (provider, event_id) is what makes webhook processing idempotent. */
class PaymentWebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean', 'processed_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOffer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
            'offered_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(DeliveryJob::class, 'delivery_job_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }
}

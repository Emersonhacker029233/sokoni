<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A Sokoni Driver account: an ordinary user plus this profile. Status,
 * approval, online state and location columns are deliberately NOT
 * fillable; they change only through DriverProfileController (onboarding),
 * DriverAvailabilityController (online/location) and the admin queue.
 */
#[Fillable([
    'user_id', 'national_id_number', 'licence_number', 'licence_expires_on',
    'terms_accepted_at', 'terms_version',
])]
#[Hidden(['national_id_number', 'national_id_image', 'licence_image'])]
class DriverProfile extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    protected function casts(): array
    {
        return [
            'national_id_number' => 'encrypted',
            'licence_expires_on' => 'date',
            'is_online' => 'boolean',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'last_located_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'flagged_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'rating_avg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function activeVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->where('is_active', true)->latestOfMany();
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(DeliveryJob::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DeliveryOffer::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(DriverPayout::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** The one job this driver is currently working, if any. */
    public function activeJob(): ?DeliveryJob
    {
        return $this->jobs()->whereIn('status', DeliveryJob::DRIVER_ACTIVE_STATUSES)->latest('id')->first();
    }

    public function hasActiveJob(): bool
    {
        return $this->jobs()->whereIn('status', DeliveryJob::DRIVER_ACTIVE_STATUSES)->exists();
    }

    /** Everything the admin needs before approving (and the app before "Submit"). */
    public function missingRequirements(): array
    {
        $vehicle = $this->activeVehicle;

        return array_keys(array_filter([
            'photo' => $this->photo === null,
            'national_id_number' => $this->national_id_number === null,
            'national_id_image' => $this->national_id_image === null,
            'licence_number' => $this->licence_number === null && $vehicle?->type !== 'bicycle',
            'licence_image' => $this->licence_image === null && $vehicle?->type !== 'bicycle',
            'vehicle' => $vehicle === null,
        ]));
    }
}

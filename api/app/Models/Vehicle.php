<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['driver_profile_id', 'type', 'plate_number', 'make', 'colour', 'is_active'])]
class Vehicle extends Model
{
    use HasFactory;

    public const TYPES = ['boda_boda', 'bajaj', 'car', 'bicycle'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** "mc 123 abc" / "MC-123-ABC" -> "MC123ABC", so the unique index catches duplicates. */
    public static function normalisePlate(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }
        $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');

        return $normalised === '' ? null : $normalised;
    }
}

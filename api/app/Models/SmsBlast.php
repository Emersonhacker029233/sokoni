<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * C6: an admin's bulk-SMS blast. Never fillable from a request — the
 * Filament page builds every field itself from typed Livewire properties,
 * the same "no #[Fillable] on an admin-only record" convention as
 * `ActivityLog` (see its own docblock).
 */
#[Fillable([])]
class SmsBlast extends Model
{
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsBlastRecipient::class);
    }

    public function isDone(): bool
    {
        return $this->sent_count + $this->failed_count >= $this->total_count;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class SmsBlastRecipient extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function blast(): BelongsTo
    {
        return $this->belongsTo(SmsBlast::class, 'sms_blast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

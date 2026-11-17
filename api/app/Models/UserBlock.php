<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "I don't want to see this person" (Apple Guideline 1.2). The blocker
 * stops seeing everything the blocked user posts, and neither can message
 * or order from the other. The blocked user is never told. See BlockList
 * for how lists are filtered.
 */
#[Fillable(['blocker_id', 'blocked_id', 'reason'])]
class UserBlock extends Model
{
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}

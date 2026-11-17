<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the 1-3 photos a buyer attaches to a return request. Stored on
 * the PRIVATE `local` disk and only ever handed out as a 30-minute signed
 * URL to the buyer, the shop and admins (App\Support\PrivateFileUrl).
 */
class ReturnRequestPhoto extends Model
{
    protected $guarded = ['*'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}

<?php

namespace App\Models;

use App\Observers\CustomerObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Customer"/"Mteja" — a buyer following a seller's shop (CLAUDE.md Part 3),
 * replacing "follower" everywhere in code and UI. A real model rather than
 * a bare pivot (like `favorites`) specifically so create/delete fire
 * Eloquent events — CustomerObserver keeps `seller_profiles.customer_count`
 * denormalised the same way ReviewObserver keeps rating_avg/rating_count.
 */
#[Fillable(['buyer_id', 'seller_id'])]
#[ObservedBy(CustomerObserver::class)]
class Customer extends Model
{
    use HasFactory;

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }
}

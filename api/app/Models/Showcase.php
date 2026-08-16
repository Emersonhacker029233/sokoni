<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Showcase"/"Onyesho" (CLAUDE.md Part 3) — a vertical full-screen product
 * video. `product_id` is non-nullable at the schema level (see the migration).
 */
#[Fillable(['seller_id', 'product_id', 'video_path', 'thumb_path', 'caption', 'duration'])]
class Showcase extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Publicly visible: seller verified, Listing still visible. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->whereHas('seller', fn (Builder $q) => $q->where('status', 'verified'))
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true)->where('is_hidden', false));
    }

    public function scopeFollowedBy(Builder $query, int $buyerId): Builder
    {
        return $query->whereHas('seller', fn (Builder $q) => $q->whereHas(
            'followers',
            fn (Builder $f) => $f->where('users.id', $buyerId)
        ));
    }
}

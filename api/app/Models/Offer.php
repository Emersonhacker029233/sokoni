<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Offer"/"Punguzo" (CLAUDE.md Part 3) — a time-limited deal on a Listing.
 * `product_id` is non-nullable at the schema level (see the migration).
 */
#[Fillable(['seller_id', 'product_id', 'discount_type', 'discount_value', 'price_snapshot', 'starts_at', 'ends_at'])]
class Offer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'price_snapshot' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    /** The sale price in whole TZS — `discount_type=percent` computes off `price_snapshot`, `fixed_price` is the sale price outright. */
    public function discountedPrice(): int
    {
        return (int) round(match ($this->discount_type) {
            'percent' => (float) $this->price_snapshot * (1 - (float) $this->discount_value / 100),
            'fixed_price' => (float) $this->discount_value,
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }

    /** Publicly visible: currently running, seller verified, Listing still visible. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->active()
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

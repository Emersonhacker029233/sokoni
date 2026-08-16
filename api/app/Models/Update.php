<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Update"/"Taarifa" (CLAUDE.md Part 3) — a 24h shop notice. `seller_id` is
 * non-nullable at the schema level (see the migration) — that FK, not this
 * class, is the actual guarantee against standalone personal content.
 */
#[Fillable(['seller_id', 'product_id', 'type', 'media_path', 'thumb_path', 'caption', 'expires_at'])]
class Update extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /** Publicly visible: not expired, the seller is verified, and any referenced Listing hasn't been pulled. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereHas('seller', fn (Builder $q) => $q->where('status', 'verified'))
            ->where(function (Builder $q) {
                $q->whereNull('product_id')->orWhereHas(
                    'product',
                    fn (Builder $p) => $p->where('is_active', true)->where('is_hidden', false)
                );
            });
    }

    /** Restricts to shops the given buyer follows — powers the "Following" tray/filter. */
    public function scopeFollowedBy(Builder $query, int $buyerId): Builder
    {
        return $query->whereHas('seller', fn (Builder $q) => $q->whereHas(
            'followers',
            fn (Builder $f) => $f->where('users.id', $buyerId)
        ));
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HidesBlockedAuthors;
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
    use HasFactory, HidesBlockedAuthors;

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'is_hidden' => 'boolean',
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

    /** Publicly visible: not hidden by a moderator, shop listed, Listing still visible. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('is_hidden'), false)
            ->whereHas('seller', fn (Builder $q) => $q->listed())
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true)->where('is_hidden', false));
    }

    public function isPubliclyVisible(): bool
    {
        return ! $this->is_hidden
            && ($this->seller?->isListed() ?? false)
            && $this->product !== null
            && $this->product->is_active
            && ! $this->product->is_hidden;
    }

    protected function withoutAuthors(Builder $query, array $userIds): Builder
    {
        return $this->withoutShopsOwnedBy($query, $userIds);
    }

    public function scopeFollowedBy(Builder $query, int $buyerId): Builder
    {
        return $query->whereHas('seller', fn (Builder $q) => $q->whereHas(
            'followers',
            fn (Builder $f) => $f->where('users.id', $buyerId)
        ));
    }
}

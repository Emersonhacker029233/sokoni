<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'seller_id', 'category_id', 'title', 'description', 'price', 'currency',
    'stock', 'condition', 'is_active', 'is_sponsored', 'sponsored_until', 'sponsor_contact_method',
])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_hidden' => 'boolean',
            'is_sponsored' => 'boolean',
            'sponsored_until' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Root-level comments only — see [[Comment::replies]] for the one level of nested replies. */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    /** Publicly visible: active, not hidden, and the seller is verified. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->whereHas('seller', fn (Builder $q) => $q->where('status', 'verified'));
    }

    /** Currently boosted — CLAUDE.md Part 3's "Sponsored" feed cards. */
    public function scopeSponsoredActive(Builder $query): Builder
    {
        return $query->where('is_sponsored', true)
            ->where('sponsored_until', '>=', now());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return $query->whereFullText(['title', 'description'], $term);
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeInCategory(Builder $query, ?int $categoryId): Builder
    {
        return $categoryId ? $query->where('category_id', $categoryId) : $query;
    }

    /** Restricts to one seller's products — powers the shop profile's product tab. */
    public function scopeForSeller(Builder $query, ?int $sellerId): Builder
    {
        return $sellerId ? $query->where('seller_id', $sellerId) : $query;
    }
}

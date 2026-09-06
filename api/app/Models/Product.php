<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'seller_id', 'category_id', 'title', 'description', 'description_sw', 'price', 'currency',
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

    /** Reports filed against this product — admin panel's "any reports against it" (CLAUDE.md admin rebuild, Section 4). */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** The website's product page strikethrough price — a Product has at most one Offer running at a time in practice, but nothing enforces that at the schema level, so this deliberately takes the most recently started one if more than one somehow overlaps. */
    public function activeOffer(): HasOne
    {
        return $this->hasOne(Offer::class)->active()->latestOfMany('starts_at');
    }

    /**
     * Falls back to the English description when no Swahili one is set —
     * true of every product row that predates description_sw. Deliberately
     * NOT named description() — that exact name collides with Eloquent's
     * getRelationshipFromMethod() magic (it calls any method matching an
     * accessed property name when the attribute isn't yet in $attributes,
     * expecting a Relation back), which crashed with an ArgumentCountError
     * the moment anything touched $product->description before the model
     * was fully hydrated. Category::name() never had this problem because
     * `name` was never itself a real column — only name_en/name_sw are.
     */
    public function localizedDescription(string $locale): ?string
    {
        return $locale === 'sw' && $this->description_sw ? $this->description_sw : $this->description;
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

    /**
     * Browsing a top-level category must include its subcategories' own
     * products, not just ones tagged with that exact id — a seller who
     * picks "TVs" under Electronics still needs to show up when a buyer
     * browses "Electronics" itself. A child category id stays an exact
     * match (it has no children of its own — categories are two levels
     * only). This mirrors ProductSearchService::categoryCounts(), which
     * already sums a parent's count from itself plus its children.
     */
    public function scopeInCategory(Builder $query, ?int $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        $category = Category::find($categoryId);
        if (! $category || $category->parent_id !== null) {
            return $query->where('category_id', $categoryId);
        }

        $ids = [$category->id, ...$category->children()->pluck('id')];

        return $query->whereIn('category_id', $ids);
    }

    /** Restricts to one seller's products — powers the shop profile's product tab. */
    public function scopeForSeller(Builder $query, ?int $sellerId): Builder
    {
        return $sellerId ? $query->where('seller_id', $sellerId) : $query;
    }
}

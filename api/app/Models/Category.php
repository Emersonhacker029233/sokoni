<?php

namespace App\Models;

use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['parent_id', 'name_en', 'name_sw', 'icon', 'image', 'sort_order', 'is_active'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Mega menu tree cache must never outlive a rename/reorder/deactivate — see CategoryCatalogService::megaMenuTree(). */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(CategoryCatalogService::MEGA_MENU_CACHE_KEY));
        static::deleted(fn () => Cache::forget(CategoryCatalogService::MEGA_MENU_CACHE_KEY));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function sellerProfiles(): HasMany
    {
        return $this->hasMany(SellerProfile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Localized name for the given locale ('en' or 'sw'), defaulting to English. */
    public function name(string $locale): string
    {
        return $locale === 'sw' ? $this->name_sw : $this->name_en;
    }

    /**
     * The one category that gets Make/Model attributes (C3, tester
     * feedback) — matched by name_en, the same stable identifier every
     * other category-specific special-case in this codebase already
     * keys on (the 2026-09-03 rename migration, CategorySeeder itself).
     * Deliberately not a new "is_vehicle"-style column: exactly one
     * category needs this today, so a column would be pure ceremony for
     * a single true row.
     */
    public function isCars(): bool
    {
        return $this->name_en === 'Cars';
    }
}

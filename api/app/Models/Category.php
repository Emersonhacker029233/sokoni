<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name_en', 'name_sw', 'icon', 'sort_order', 'is_active'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shop_name', 'handle', 'bio', 'category_id', 'whatsapp', 'lat', 'lng',
    'address', 'region', 'district', 'nida_number', 'nida_image',
    'licence_file', 'show_whatsapp',
])]
class SellerProfile extends Model
{
    use HasFactory;

    /** 3-20 chars, lowercase letters/digits/underscore. */
    public const HANDLE_PATTERN = '/^[a-z0-9_]{3,20}$/';

    /** Handles no seller may register, either reserved for the platform or offensive/confusing. */
    public const RESERVED_HANDLES = [
        'admin', 'api', 'sokoni', 'support', 'help', 'about', 'contact',
        'terms', 'privacy', 'login', 'signup', 'settings', 'null', 'undefined',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'verified_at' => 'datetime',
            'rating_avg' => 'decimal:2',
            'show_whatsapp' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'seller_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', 'verified');
    }
}

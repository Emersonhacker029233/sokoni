<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// B1/B2 (tester feedback): nida_image and licence_file are deliberately
// no longer fillable — the columns themselves stay (already-verified
// sellers keep whatever evidence they'd uploaded; dropping the columns
// would destroy real production data for no reason), but nothing writes
// to them anymore. Verification is now a typed NIDA number alone.
#[Fillable([
    'shop_name', 'logo', 'handle', 'bio', 'bio_sw', 'category_id', 'whatsapp', 'lat', 'lng',
    'address', 'region', 'district', 'nida_number', 'show_whatsapp', 'opening_hours',
])]
class SellerProfile extends Model
{
    // C2 (tester feedback): a deleted user's shop must be recoverable
    // right alongside them — see UsersTable's cascading delete action.
    use HasFactory, SoftDeletes;

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
            'opening_hours' => 'array',
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

    public function updates(): HasMany
    {
        return $this->hasMany(Update::class, 'seller_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'seller_id');
    }

    public function showcases(): HasMany
    {
        return $this->hasMany(Showcase::class, 'seller_id');
    }

    /** Buyers following this shop — "Customer"/"Mteja" (CLAUDE.md Part 3). */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customers', 'seller_id', 'buyer_id')->withTimestamps();
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /** Language audit (client feedback): the shop dashboard used to render this raw via `ucfirst()` — see Order::statusLabel()'s docblock for the same reasoning. */
    public function statusLabel(): string
    {
        return __('site.seller_status_'.$this->status);
    }

    /** Falls back to the English bio when no Swahili one is set — true of every seller row that predates bio_sw. Named localizedBio(), not bio() — see Product::localizedDescription()'s docblock for why colliding with the raw column name is a real crash risk, not just a style nit. */
    public function localizedBio(string $locale): ?string
    {
        return $locale === 'sw' && $this->bio_sw ? $this->bio_sw : $this->bio;
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

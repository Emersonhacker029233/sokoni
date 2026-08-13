<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'phone', 'password', 'avatar', 'provider', 'provider_id',
    'locale', 'fcm_token', 'terms_accepted_at', 'terms_version',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'banned_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'buyer_id');
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorites')->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * Deliberately queries fresh rather than reading the (possibly already
     * loaded-as-null, now-stale) `sellerProfile` relation — this gates
     * authorization, so it must reflect a seller profile created earlier
     * in the same request/object lifecycle, not a cached miss from before
     * it existed.
     */
    public function isSeller(): bool
    {
        return $this->sellerProfile()->exists();
    }

    public function isVerifiedSeller(): bool
    {
        return $this->sellerProfile()->where('status', 'verified')->exists();
    }

    /** Same freshness rationale as isSeller() — for "is this the owning seller?" authorization checks. */
    public function sellerProfileId(): ?int
    {
        return $this->sellerProfile()->value('id');
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }
}

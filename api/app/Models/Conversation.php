<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['buyer_id', 'seller_id', 'product_id', 'order_id', 'last_message_at'])]
class Conversation extends Model
{
    use HasFactory;

    /** How long a "typing" signal stays valid after the client sends it. */
    public const TYPING_TTL_SECONDS = 6;

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'buyer_typing_until' => 'datetime',
            'seller_typing_until' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /** True if $user is either party to this conversation. */
    public function involves(User $user): bool
    {
        return $this->buyer_id === $user->id || $this->seller->user_id === $user->id;
    }

    /** Whether the party opposite $user is currently (within the TTL) typing. */
    public function otherPartyTyping(User $user): bool
    {
        $until = $this->buyer_id === $user->id ? $this->seller_typing_until : $this->buyer_typing_until;

        return $until !== null && $until->isFuture();
    }

    /** Marks $user as currently typing, from the caller's own role. */
    public function markTyping(User $user): void
    {
        $column = $this->buyer_id === $user->id ? 'buyer_typing_until' : 'seller_typing_until';
        $this->forceFill([$column => now()->addSeconds(self::TYPING_TTL_SECONDS)])->save();
    }
}

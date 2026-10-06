<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** See this table's own migration docblock for the full "recognised device" design. */
class TrustedDevice extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'label', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generates a new device token, stores only its hash, and returns the
     * plain value — the only time it's ever visible, same one-shot
     * pattern Sanctum's own token creation uses. The caller is
     * responsible for giving this to the client to store securely
     * on-device; it is never persisted or logged anywhere in plain form.
     */
    public static function issueFor(User $user, ?string $label = null): string
    {
        $plain = Str::random(64);

        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $plain),
            'label' => $label,
            'last_used_at' => now(),
        ]);

        return $plain;
    }

    /**
     * Looks up a device token presented at sign-in by its hash, the same
     * approach Sanctum's own `PersonalAccessToken::findToken()` uses
     * (plain SHA-256 plus a unique index, not `Hash::check`/bcrypt) — this
     * is a long, random, high-entropy token, not a human-chosen password,
     * so a fast hash is both sufficient and necessary (bcrypt would be far
     * too slow to verify on every sign-in attempt at scale).
     */
    public static function find(string $plain): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $plain))->first();
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}

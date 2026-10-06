<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "recognised device" (CLAUDE.md Part 2.2) — a durable, random token
 * generated and stored securely on-device the first time a sign-in
 * completes a full password+SMS-code challenge, then presented silently
 * on every later sign-in attempt from that same device so a correct
 * password alone is enough (skipping the SMS step) as long as the token
 * still matches. Deliberately device-token-based, not IP-based (CLAUDE.md
 * is explicit: "not by IP") — a phone changing networks must not force a
 * fresh challenge, and a shared/spoofed IP must not grant trust. Only the
 * SHA-256 hash is stored, the same pattern Sanctum's own
 * `personal_access_tokens` table uses for its own tokens, so a stolen
 * database dump can't be replayed as a valid device token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('label')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};

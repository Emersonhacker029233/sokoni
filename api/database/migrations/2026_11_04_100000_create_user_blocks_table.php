<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apple Guideline 1.2 (rejection of iOS 1.0 (7)): "a mechanism for users
 * to block abusive users". One row per (blocker, blocked) pair; blocking a
 * shop blocks the user who owns it.
 *
 * Additive only. Every index is integer-only (16 bytes at most), well
 * under the 1000-byte key limit of MyISAM-limited hosts. `reason` is a
 * short code from the app's reason list and is never indexed.
 *
 * Rollback: `php artisan migrate:rollback --step=1` while this is the
 * newest batch, or by hand: `DROP TABLE user_blocks;`. Nothing else
 * references the table, so dropping it loses only the block list itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_blocks')) {
            return;
        }

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 50)->nullable();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};

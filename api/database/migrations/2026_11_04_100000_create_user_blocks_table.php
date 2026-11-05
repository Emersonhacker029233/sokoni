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

        // MySQL/MariaDB can't roll back DDL, and the keys are separate
        // statements after CREATE TABLE. If one failed, a retry would see
        // the table, skip it and record this migration as done without its
        // keys. So a failure removes the half-made table and rethrows: the
        // error shows in the log and a retry starts clean.
        try {
            Schema::create('user_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 50)->nullable();
                $table->timestamps();

                $table->unique(['blocker_id', 'blocked_id']);
                $table->index('blocked_id');
            });
        } catch (\Throwable $e) {
            rescue(fn () => Schema::dropIfExists('user_blocks'), report: false);
            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};

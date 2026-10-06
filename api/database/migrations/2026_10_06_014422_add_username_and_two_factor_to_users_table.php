<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Username/password rework (Part D). `password` already exists on `users`
 * (nullable, added for the Filament admin/staff login — see that
 * migration's own comment) and is reused as-is, not duplicated. `username`
 * is nullable + unique for the same reason `password` already is: every
 * existing account has neither today, and nullable-unique allows any
 * number of NULL rows without a collision, so no backfill is required for
 * existing users to keep working. `two_factor_enabled` defaults to false
 * (CLAUDE.md Part 2.4) — off until a user opts in, never auto-enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('phone');
            $table->boolean('two_factor_enabled')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'two_factor_enabled']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // `banned_at` already exists (CLAUDE.md's schema). These two
            // fields make it match CLAUDE.md feature 11's four admin
            // actions ("hide, warn, suspend or ban with a logged reason"):
            // `ban_reason` persists the reason instead of it only living
            // in an ephemeral push-notification body, and `banned_until`
            // turns the same `banned_at` flag into either a temporary
            // suspension (set) or a permanent ban (left null).
            $table->string('ban_reason')->nullable()->after('banned_at');
            $table->timestamp('banned_until')->nullable()->after('ban_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ban_reason', 'banned_until']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only meaningful alongside is_admin=true, which stays the
            // actual panel-access gate (User::canAccessPanel()) — this is
            // *which* level of access an admin account has, not a second
            // gate. 'admin' = full access; 'staff' = moderation and
            // verification only, enforced by policies, not hidden menu
            // items (CLAUDE.md admin rebuild, Section 6).
            $table->enum('role', ['admin', 'staff'])->nullable()->after('is_admin');
        });

        // Backfill: every pre-existing admin account keeps full access
        // rather than silently losing it because a new nullable column
        // defaulted to null.
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

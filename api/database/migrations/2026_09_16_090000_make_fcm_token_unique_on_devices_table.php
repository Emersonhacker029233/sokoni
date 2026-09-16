<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 5 (client feedback): "Push notification tokens must follow the
 * active account correctly, so notifications aren't delivered to the
 * wrong one." The original `unique(['user_id', 'fcm_token'])` allowed
 * the exact opposite: the same physical device's FCM token registered
 * under two different users at once, both rows valid simultaneously —
 * fine when a device could only ever belong to one signed-in account
 * for life, wrong now that switching accounts on the same device is a
 * real, supported flow. A token is one physical device install; it
 * should only ever route to whichever account most recently registered
 * it, which a plain unique on `fcm_token` alone makes the database
 * itself enforce, not just application code remembering to check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'fcm_token']);
        });

        // Belt-and-suspenders against any pre-existing duplicate
        // fcm_token rows across different users (exactly the state this
        // migration exists to stop being possible): keep only the most
        // recently seen row per fcm_token before adding the new
        // constraint, so this migration can never fail against real
        // production data carrying the old, looser invariant.
        $duplicateTokens = \Illuminate\Support\Facades\DB::table('devices')
            ->select('fcm_token')
            ->groupBy('fcm_token')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('fcm_token');

        foreach ($duplicateTokens as $token) {
            $rows = \Illuminate\Support\Facades\DB::table('devices')
                ->where('fcm_token', $token)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->get(['id']);

            \Illuminate\Support\Facades\DB::table('devices')
                ->whereIn('id', $rows->skip(1)->pluck('id'))
                ->delete();
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->unique('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['fcm_token']);
            $table->unique(['user_id', 'fcm_token']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 *
 * Production failure (MariaDB): `devices_user_id_fcm_token_unique` is
 * also the ONLY index covering `user_id`, so it's what backs the
 * `devices_user_id_foreign` key — MySQL/MariaDB refuse to drop an index
 * a foreign key still depends on ("Cannot drop index ... needed in a
 * foreign key constraint", error 1553). Fixed by giving the foreign key
 * its own plain index on `user_id` FIRST, so the composite unique is
 * free to drop afterwards.
 *
 * Every step below checks the schema's actual current state before
 * acting (via Schema::hasIndex(), not just try/catch) rather than
 * assuming a clean slate, since this migration already partially ran on
 * production before failing — re-running it must be a no-op wherever a
 * step already landed, not an error.
 */
return new class extends Migration
{
    private const TABLE = 'devices';

    private const OLD_COMPOSITE_UNIQUE = 'devices_user_id_fcm_token_unique';

    private const USER_ID_INDEX = 'devices_user_id_index';

    private const NEW_FCM_TOKEN_UNIQUE = 'devices_fcm_token_unique';

    public function up(): void
    {
        // 1) Give the user_id foreign key its own backing index before
        // touching the composite unique it currently relies on.
        if (! $this->indexExists(self::USER_ID_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('user_id', self::USER_ID_INDEX);
            });
        }

        // 2) Now safe to drop — the foreign key has switched to the
        // plain index above.
        if ($this->indexExists(self::OLD_COMPOSITE_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::OLD_COMPOSITE_UNIQUE);
            });
        }

        // 3) A unique constraint fails on data, not schema, if any
        // fcm_token is currently duplicated across rows (exactly the
        // state this migration exists to stop being possible) — keep
        // only the most recently seen row per token before adding it.
        $this->removeDuplicateFcmTokens();

        // 4) The new invariant this whole migration is for.
        if (! $this->indexExists(self::NEW_FCM_TOKEN_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique('fcm_token', self::NEW_FCM_TOKEN_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists(self::NEW_FCM_TOKEN_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::NEW_FCM_TOKEN_UNIQUE);
            });
        }

        if (! $this->indexExists(self::OLD_COMPOSITE_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['user_id', 'fcm_token'], self::OLD_COMPOSITE_UNIQUE);
            });
        }

        // The composite unique backs the foreign key again now, so the
        // plain index is redundant — drop it, mirroring the original
        // (pre-this-migration) schema exactly.
        if ($this->indexExists(self::USER_ID_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::USER_ID_INDEX);
            });
        }
    }

    /**
     * Keeps the most-recently-seen row per duplicated `fcm_token`
     * (falling back to the highest id on a `last_seen_at` tie/null) and
     * deletes the rest, logging how many were removed.
     */
    private function removeDuplicateFcmTokens(): void
    {
        $duplicateTokens = DB::table(self::TABLE)
            ->select('fcm_token')
            ->groupBy('fcm_token')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('fcm_token');

        $removed = 0;

        foreach ($duplicateTokens as $token) {
            $rows = DB::table(self::TABLE)
                ->where('fcm_token', $token)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->get(['id']);

            $staleIds = $rows->skip(1)->pluck('id');

            DB::table(self::TABLE)->whereIn('id', $staleIds)->delete();

            $removed += $staleIds->count();
        }

        if ($removed > 0) {
            Log::info("Migration 2026_09_16_090000: removed {$removed} duplicate device row(s) across {$duplicateTokens->count()} fcm_token(s) before adding the unique constraint.");
        }
    }

    /**
     * Whether an index with this exact name currently exists on the
     * table — checked via Schema::hasIndex() (driver-agnostic: backed by
     * information_schema on MySQL/MariaDB, PRAGMA on SQLite, etc.) so
     * this migration can be re-run safely after a partial failure, and
     * so it still runs correctly under the test suite's SQLite
     * connection, not just production MariaDB.
     */
    private function indexExists(string $indexName): bool
    {
        return Schema::hasIndex(self::TABLE, $indexName);
    }
};

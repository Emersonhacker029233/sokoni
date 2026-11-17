<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence for the 24-hour moderation promise (Apple Guideline 1.2):
 * `alerted_at` is when the moderators' email + admin-panel alert went out
 * for a new report, `resolved_at` is when an admin acted on it. Together
 * with `created_at` they answer "how long did this report wait?".
 *
 * Additive only: two nullable timestamps, no index, no change to the
 * existing `status`/`resolved_by`/`resolution` columns or their meaning.
 * Existing reports keep null in both.
 *
 * Rollback: `php artisan migrate:rollback` while this batch is the newest,
 * or by hand: `ALTER TABLE reports DROP COLUMN alerted_at, DROP COLUMN
 * resolved_at;`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'alerted_at')) {
                $table->timestamp('alerted_at')->nullable();
            }
            if (! Schema::hasColumn('reports', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['alerted_at', 'resolved_at'],
            fn (string $column) => Schema::hasColumn('reports', $column),
        ));

        if ($columns !== []) {
            Schema::table('reports', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};

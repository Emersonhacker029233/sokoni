<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who caused a notification (the message sender, the shop that posted an
 * update, ...), so a user's inbox can leave out everything from people
 * they blocked. Null for system notifications and for every notification
 * written before this column existed; those are always shown.
 *
 * Additive only: one nullable column. Its index is a single BIGINT
 * (8 bytes). On MyISAM the foreign key is parsed and ignored by MySQL,
 * which is why the column also gets an explicit index.
 *
 * Rollback: `php artisan migrate:rollback` while this batch is the newest.
 * By hand on InnoDB: `ALTER TABLE app_notifications DROP FOREIGN KEY
 * app_notifications_actor_id_foreign;` then `ALTER TABLE app_notifications
 * DROP COLUMN actor_id;`. On MyISAM there is no foreign key to drop: run
 * only the DROP COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('app_notifications', 'actor_id')) {
            return;
        }

        // Column, foreign key and index are separate statements, and
        // MySQL/MariaDB can't roll back DDL. If the key or index failed, a
        // retry would see the column, skip it and record this migration as
        // done without them. So a failure takes the column back out and
        // rethrows: the error shows in the log and a retry starts clean.
        try {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->index('actor_id');
            });
        } catch (\Throwable $e) {
            rescue(fn () => $this->removeActorId(), report: false);
            throw $e;
        }
    }

    public function down(): void
    {
        $this->removeActorId();
    }

    private function removeActorId(): void
    {
        if (! Schema::hasColumn('app_notifications', 'actor_id')) {
            return;
        }

        // Matched by column, not name: SQLite keeps foreign keys unnamed,
        // and MyISAM keeps none at all.
        $hasForeignKey = collect(Schema::getForeignKeys('app_notifications'))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['actor_id']);
        if ($hasForeignKey) {
            Schema::table('app_notifications', fn (Blueprint $table) => $table->dropForeign(['actor_id']));
        }

        $hasIndex = collect(Schema::getIndexes('app_notifications'))
            ->contains(fn (array $index) => $index['columns'] === ['actor_id'] && ! $index['primary']);
        if ($hasIndex) {
            Schema::table('app_notifications', fn (Blueprint $table) => $table->dropIndex(['actor_id']));
        }

        Schema::table('app_notifications', fn (Blueprint $table) => $table->dropColumn('actor_id'));
    }
};

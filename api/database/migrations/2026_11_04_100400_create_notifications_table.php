<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's database-notifications table, used only by the admin panel's
 * bell (Filament database notifications) to alert moderators of each new
 * report. Unrelated to `app_notifications`, the app users' own inbox.
 *
 * Laravel's stock version uses `morphs('notifiable')`, whose index is
 * 255 x 4 + 8 = 1028 bytes in utf8mb4: over the 1000-byte key limit of
 * MyISAM. Here the indexed type column is capped at 191 characters
 * (191 x 4 + 8 = 772 bytes); the longest class name stored in it is
 * `App\Models\User`. The UUID primary key is 36 x 4 = 144 bytes.
 *
 * Rollback: `php artisan migrate:rollback` while this batch is the newest,
 * or by hand: `DROP TABLE notifications;`. Only the admin bell's history
 * is lost; the email alerts are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 191);
            $table->string('notifiable_type', 191);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

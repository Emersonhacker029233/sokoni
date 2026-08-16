<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real in-app notification inbox (CLAUDE.md Part 3's top-bar
     * Notifications icon) — every event that already calls `PushNotifier`
     * (new order, status change, new message, new Update/Offer) now also
     * lands here, so the icon opens a real list rather than a dead button.
     * Named `app_notifications`, not `notifications`, to stay clear of
     * Laravel's own conventional `notifications` table/`DatabaseNotification`
     * shape (UUID id, polymorphic `notifiable`) — this app never adopted
     * that built-in system, so reusing its table name would only invite
     * confusion if it's ever adopted later.
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};

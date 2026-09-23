<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 4 (client feedback): Settings' "Notifications" section — one
     * toggle each for orders, messages and offers from followed shops.
     * "Marketing" reuses the existing `marketing_consent` column (already
     * collected at registration) rather than adding a redundant one — see
     * AuthController::updateNotificationPreferences(). All default true:
     * these gate pushes a user already expects (their own orders,
     * messages, a shop they chose to follow) — opt-out, not opt-in.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_orders')->default(true)->after('marketing_consent');
            $table->boolean('notify_messages')->default(true)->after('notify_orders');
            $table->boolean('notify_offers')->default(true)->after('notify_messages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_orders', 'notify_messages', 'notify_offers']);
        });
    }
};

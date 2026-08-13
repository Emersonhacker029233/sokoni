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
        Schema::table('conversations', function (Blueprint $table) {
            // CLAUDE.md feature 5 calls for a typing indicator despite the
            // no-WebSocket, 5s-polling transport — a per-party "typing
            // until" timestamp, refreshed while the user types and read on
            // every poll, is the real (not simulated) equivalent: it just
            // has ~5s of latency like the rest of the chat transport does.
            $table->timestamp('buyer_typing_until')->nullable()->after('last_message_at');
            $table->timestamp('seller_typing_until')->nullable()->after('buyer_typing_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['buyer_typing_until', 'seller_typing_until']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLAUDE.md Part 3: boosted/sponsored Listings in the "For You" feed —
     * no payment flow yet, so this is a plain seller/admin-toggleable flag
     * (see `ProductPolicy::boost`) rather than anything billing-related.
     * `sponsor_contact_method` records which action the seller chose to
     * surface on the sponsored card (chat/whatsapp/call), mirroring how
     * Instagram business posts expose one contact action per boosted post.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->after('is_hidden');
            $table->timestamp('sponsored_until')->nullable()->after('is_sponsored');
            $table->string('sponsor_contact_method')->nullable()->after('sponsored_until');
            $table->index(['is_sponsored', 'sponsored_until']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_sponsored', 'sponsored_until']);
            $table->dropColumn(['is_sponsored', 'sponsored_until', 'sponsor_contact_method']);
        });
    }
};

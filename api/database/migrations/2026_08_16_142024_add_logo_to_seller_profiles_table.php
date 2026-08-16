<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Instagram-style shop header (CLAUDE.md Parts 3-4: "circular shop
     * avatar" on both the feed card and the shop profile) needs a shop
     * logo/avatar image — the original schema never had one (only
     * `users.avatar`, which is the signed-in person's own account photo,
     * not the shop's).
     */
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('shop_name');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};

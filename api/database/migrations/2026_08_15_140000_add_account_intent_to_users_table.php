<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presentation-only signal for the one-question "buy / sell / decide
     * later" intent screen shown once, right after a brand-new account's
     * first sign-in (CLAUDE.md Part 2 item 2) — never for returning users,
     * and never a second time for the same user once answered. Nullable:
     * every existing user (created before this column existed) reads as
     * null here too, but that's harmless — the intent screen is only ever
     * triggered by the `is_new_account` signal on the sign-in response
     * itself, not by checking this column being null, so an old user
     * never sees it retroactively. One account, one optional
     * seller_profile — this is not a user-type field.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_intent')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_intent');
        });
    }
};

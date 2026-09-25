<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 5 (client feedback): "add a language selector [to the
     * admin]... the switcher should change the locale properly." A
     * separate column from the existing `locale` on purpose — that one
     * now defaults every user to `'sw'` (the language round, Part 1),
     * which is the right default for buyer/seller-facing communication
     * but not necessarily what an admin wants their own internal tool
     * rendered in; reusing it here would make the admin panel silently
     * inherit that same customer-facing default rather than genuinely
     * defaulting to English as this round asks for. Null means "no
     * explicit choice yet" — the admin panel defaults to English for
     * that case (see SetAdminLocale), never derived from `locale`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_locale')->nullable()->after('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_locale');
        });
    }
};

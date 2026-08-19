<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public website shop profile pages (sokoni.co.tz/@handle) need real
 * opening hours for schema.org LocalBusiness markup, not a guess — added
 * here and to the app's Edit Profile screen (same migration serves both,
 * one source of truth). Shape: a JSON object keyed by lowercase weekday
 * ('monday'..'sunday'), each value either null (closed) or {open, close}
 * in 24h "HH:MM" — validated in App\Support\OpeningHours, not the schema,
 * same reasoning as Comment's one-level-of-replies rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('show_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }
};

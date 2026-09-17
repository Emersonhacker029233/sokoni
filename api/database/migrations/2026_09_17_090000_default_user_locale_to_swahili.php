<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Language audit (client feedback): "Kiswahili is the primary language
 * of this marketplace's users... it must be what a visitor sees first."
 * `users.locale` defaulted to 'en' at the database level — harmless for
 * any row a registration flow explicitly sets a locale on, but the
 * fallback every OTHER path (an older row, a future write that forgets
 * to pass one) lands on. Flipped to match every other default changed
 * in this round (config('app.locale'), SetWebLocale::DEFAULT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('sw')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('en')->change();
        });
    }
};

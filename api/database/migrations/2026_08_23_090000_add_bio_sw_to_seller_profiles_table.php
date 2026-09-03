<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `bio` has only ever been a single field — English, in practice, since
 * nothing in the codebase branches on locale for it. Categories already
 * established the `{field}_en`/`{field}_sw` convention for genuinely
 * bilingual content (`name_en`/`name_sw`); rather than rename the existing
 * `bio` column (a breaking change rippling through the API resource, the
 * Filament forms, and every existing seller row), this adds `bio_sw` as a
 * new nullable column alongside it. A row with no `bio_sw` set — every
 * real seller today — keeps showing `bio` regardless of locale, exactly
 * as before; only rows that explicitly set `bio_sw` (the demo shops)
 * get a real Swahili bio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->text('bio_sw')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn('bio_sw');
        });
    }
};

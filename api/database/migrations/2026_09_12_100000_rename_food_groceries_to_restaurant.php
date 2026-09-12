<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C1 (client feedback): "Food & Groceries" -> "Restaurant", on the website
 * and the app. Same pattern as the 2026-09-03 rename migration and for the
 * same reason: an UPDATE by the current name_en, in place, so every
 * product's category_id keeps pointing at the same row rather than a
 * fresh insert silently orphaning them under the old name. CategorySeeder
 * is also updated to the new canonical name (for a fresh install), but a
 * live database goes through this migration, not a reseed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('name_en', 'Food & Groceries')
            ->update(['name_en' => 'Restaurant', 'name_sw' => 'Mkahawa']);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('name_en', 'Restaurant')
            ->update(['name_en' => 'Food & Groceries', 'name_sw' => 'Chakula na Vyakula']);
    }
};

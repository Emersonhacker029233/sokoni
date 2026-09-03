<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Client request (2026-09-03): rename two existing categories and add two
 * new ones, without breaking any product that already references the
 * renamed rows' `category_id`.
 *
 * Renames update the existing row *in place* (matched by its current
 * name_en) rather than going through CategorySeeder's updateOrCreate —
 * that method is keyed on name_en, so once the name itself is what's
 * changing, updateOrCreate can only ever see "no row with this new name"
 * and insert a duplicate, leaving the real row (and every product
 * pointing at its id) silently orphaned under its old name forever. A
 * plain UPDATE by the old name is the only way to actually rename without
 * losing those references.
 *
 * CategorySeeder itself is also updated to the new canonical list (for a
 * fresh install), but a fresh seed was never assumed safe to run here —
 * this migration is what a database that's already live goes through.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('name_en', 'Agriculture')
            ->update(['name_en' => 'Cereal & Legume', 'name_sw' => 'Nafaka na Mikunde']);

        DB::table('categories')
            ->where('name_en', 'Construction & Hardware')
            ->update(['name_en' => 'Hardware', 'name_sw' => 'Vifaa vya Ujenzi', 'icon' => 'hardware']);

        if (! DB::table('categories')->where('name_en', 'Real Estate')->exists()) {
            DB::table('categories')->insert([
                'name_en' => 'Real Estate',
                'name_sw' => 'Nyumba na Viwanja',
                'icon' => 'home_work',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('categories')->where('name_en', 'Kids')->exists()) {
            DB::table('categories')->insert([
                'name_en' => 'Kids',
                'name_sw' => 'Watoto',
                'icon' => 'child_care',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Re-sequence every top-level category to the new canonical order
        // (CategorySeeder::CATEGORIES) — "Other" always last, "Real Estate"
        // third, everything else keeping its existing relative order.
        $order = [
            'Electronics', 'Fashion', 'Real Estate', 'Kids', 'Food & Groceries',
            'Home & Furniture', 'Beauty & Health', 'Phones & Accessories',
            'Vehicles & Parts', 'Cereal & Legume', 'Hardware', 'Services', 'Other',
        ];
        foreach ($order as $index => $nameEn) {
            DB::table('categories')->where('name_en', $nameEn)->whereNull('parent_id')->update(['sort_order' => $index]);
        }
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('name_en', 'Cereal & Legume')
            ->update(['name_en' => 'Agriculture', 'name_sw' => 'Kilimo']);

        DB::table('categories')
            ->where('name_en', 'Hardware')
            ->update(['name_en' => 'Construction & Hardware', 'name_sw' => 'Ujenzi na Vifaa']);

        DB::table('categories')->where('name_en', 'Real Estate')->delete();
        DB::table('categories')->where('name_en', 'Kids')->delete();
    }
};

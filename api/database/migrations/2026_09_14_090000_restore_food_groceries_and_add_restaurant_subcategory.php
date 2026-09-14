<?php

use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Part 2 (client feedback, urgent follow-up to C1): "Restaurant" was
 * implemented as a straight in-place rename of the top-level "Food &
 * Groceries" row (see 2026_09_12_100000_rename_food_groceries_to_restaurant),
 * which was wrong — Restaurant should be a subcategory of Food &
 * Groceries, and Food & Groceries needed to come back as its own
 * top-level category.
 *
 * The evidence that caught it: the one shop given "Restaurant" as its
 * category after that rename went live — "Sinza Fresh Grocers", "Rice,
 * cooking oil, sugar and flour... a Sinza family shop" — is plainly a
 * grocer, not a restaurant (fixed alongside this in DemoSeeder), and the
 * category's own pre-existing subcategories (Fresh Produce, Rice &
 * Grains, Cooking Oil, Beverages, Snacks, Spices, Bakery) are all
 * grocery items the rename never touched. No product or seller in this
 * database has been shown to genuinely be a restaurant.
 *
 * Fixed by renaming the SAME row back rather than creating a second Food
 * & Groceries row: every product, subcategory, and anything else that
 * currently points at this category id via a foreign key keeps pointing
 * at the exact same id, so there is no separate "move products back"
 * UPDATE to write at all — renaming the row back to its original
 * identity (name, translation, and slug, since slugs are computed from
 * name_en and never stored) IS that move, for every product regardless
 * of how many there are. A brand new, initially-empty child row is then
 * created for Restaurant, since nothing in the data has ever shown a
 * genuine restaurant to move there — if a real one exists on production,
 * moving its specific products to the new child id is a one-off admin
 * action this migration has no data basis to make for it.
 *
 * Safe to run against the current production state (previous rename
 * already applied): the "find the top-level row currently named
 * Restaurant" lookup below simply finds nothing to rename if that rename
 * was never applied in the first place (a fresh install, or one already
 * seeded off the corrected CategorySeeder), and the "create the
 * Restaurant child" step checks for an existing row first so re-running
 * this migration can never duplicate it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mistakenlyRenamed = DB::table('categories')
            ->where('name_en', 'Restaurant')
            ->whereNull('parent_id')
            ->first();

        if ($mistakenlyRenamed) {
            DB::table('categories')
                ->where('id', $mistakenlyRenamed->id)
                ->update(['name_en' => 'Food & Groceries', 'name_sw' => 'Chakula na Vyakula']);
            $parentId = $mistakenlyRenamed->id;
        } else {
            $parentId = DB::table('categories')
                ->where('name_en', 'Food & Groceries')
                ->whereNull('parent_id')
                ->value('id');
        }

        // Neither state exists — an unrelated/fresh schema with no Food &
        // Groceries category at all. Nothing sensible to attach a
        // Restaurant subcategory to.
        if ($parentId === null) {
            return;
        }

        $restaurantChildExists = DB::table('categories')
            ->where('parent_id', $parentId)
            ->where('name_en', 'Restaurant')
            ->exists();

        if (! $restaurantChildExists) {
            DB::table('categories')->insert([
                'parent_id' => $parentId,
                'name_en' => 'Restaurant',
                'name_sw' => 'Mkahawa',
                'icon' => null,
                'sort_order' => 7, // after the seven existing grocery subcategories
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Raw DB::table writes bypass Category's own saved/deleted model
        // hooks (see CategoryCatalogService's docblock), which is what
        // normally busts this cache — clear it explicitly so the mega
        // menu doesn't keep showing the wrong structure for up to its own
        // hour-long TTL after this deploys.
        Cache::forget(CategoryCatalogService::MEGA_MENU_CACHE_KEY);
    }

    public function down(): void
    {
        $foodGroceries = DB::table('categories')
            ->where('name_en', 'Food & Groceries')
            ->whereNull('parent_id')
            ->first();

        if (! $foodGroceries) {
            return;
        }

        DB::table('categories')
            ->where('parent_id', $foodGroceries->id)
            ->where('name_en', 'Restaurant')
            ->delete();

        DB::table('categories')
            ->where('id', $foodGroceries->id)
            ->update(['name_en' => 'Restaurant', 'name_sw' => 'Mkahawa']);

        Cache::forget(CategoryCatalogService::MEGA_MENU_CACHE_KEY);
    }
};

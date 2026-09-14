<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Part 2 (client feedback): exercises
 * 2026_09_14_090000_restore_food_groceries_and_add_restaurant_subcategory's
 * actual up()/down() directly against a hand-built "previous rename
 * already applied on production" state — CategorySeeder-based tests
 * elsewhere in this suite only ever see the CORRECTED structure (a fresh
 * RefreshDatabase run has no category rows yet when migrations execute,
 * so the migration's own recovery branch — "find the mistakenly-renamed
 * row and revert it" — never actually runs under them). The one thing
 * this migration exists to prove — that it's safe against real
 * production data, and that every product/subcategory keeps its
 * category_id unchanged — needs the pre-state built by hand instead.
 */
class RestoreFoodGroceriesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = __DIR__.'/../../../database/migrations/2026_09_14_090000_restore_food_groceries_and_add_restaurant_subcategory.php';

    private function migration(): Migration
    {
        return require self::MIGRATION_PATH;
    }

    /** Simulates exactly what 2026_09_12_100000's bad rename left behind. */
    private function seedMistakenState(): int
    {
        $restaurantId = DB::table('categories')->insertGetId([
            'parent_id' => null,
            'name_en' => 'Restaurant',
            'name_sw' => 'Mkahawa',
            'icon' => 'restaurant',
            'sort_order' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['Fresh Produce' => 'Mazao Mabichi', 'Rice & Grains' => 'Mchele na Nafaka'] as $en => $sw) {
            DB::table('categories')->insert([
                'parent_id' => $restaurantId,
                'name_en' => $en,
                'name_sw' => $sw,
                'icon' => null,
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $restaurantId;
    }

    public function test_up_renames_the_mistaken_top_level_row_back_to_food_and_groceries(): void
    {
        $id = $this->seedMistakenState();

        $this->migration()->up();

        $this->assertDatabaseHas('categories', [
            'id' => $id,
            'name_en' => 'Food & Groceries',
            'name_sw' => 'Chakula na Vyakula',
            'parent_id' => null,
        ]);
    }

    public function test_up_creates_restaurant_as_a_new_child_of_the_restored_parent(): void
    {
        $id = $this->seedMistakenState();

        $this->migration()->up();

        $this->assertDatabaseHas('categories', [
            'name_en' => 'Restaurant',
            'name_sw' => 'Mkahawa',
            'parent_id' => $id,
        ]);
    }

    public function test_up_preserves_every_products_category_id_unchanged(): void
    {
        $id = $this->seedMistakenState();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $id]);

        $this->migration()->up();

        // The product never moved rows — it's the CATEGORY that was
        // renamed back underneath it, in place. This is the whole reason
        // this migration needs no separate "move products back" query.
        $this->assertSame($id, $product->fresh()->category_id);
        $this->assertSame('Food & Groceries', Category::find($id)->name_en);
    }

    public function test_up_preserves_the_existing_grocery_subcategories_under_the_same_parent(): void
    {
        $id = $this->seedMistakenState();

        $this->migration()->up();

        $this->assertDatabaseHas('categories', ['name_en' => 'Fresh Produce', 'parent_id' => $id]);
        $this->assertDatabaseHas('categories', ['name_en' => 'Rice & Grains', 'parent_id' => $id]);
    }

    public function test_up_is_safe_to_run_twice_without_duplicating_the_restaurant_child(): void
    {
        $this->seedMistakenState();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, Category::where('name_en', 'Restaurant')->count());
    }

    /** A fresh install seeded off the already-corrected CategorySeeder — no mistaken row to find. */
    public function test_up_is_a_no_op_when_food_and_groceries_already_exists_correctly(): void
    {
        $id = DB::table('categories')->insertGetId([
            'parent_id' => null,
            'name_en' => 'Food & Groceries',
            'name_sw' => 'Chakula na Vyakula',
            'icon' => 'restaurant',
            'sort_order' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertSame(1, Category::where('name_en', 'Food & Groceries')->whereNull('parent_id')->count());
        $this->assertDatabaseHas('categories', ['name_en' => 'Restaurant', 'parent_id' => $id]);
    }

    public function test_down_deletes_the_restaurant_child_and_reapplies_the_mistaken_rename(): void
    {
        $id = $this->seedMistakenState();
        $this->migration()->up();

        $this->migration()->down();

        $this->assertDatabaseHas('categories', ['id' => $id, 'name_en' => 'Restaurant', 'parent_id' => null]);
        $this->assertDatabaseMissing('categories', ['name_en' => 'Restaurant', 'parent_id' => $id]);
    }
}

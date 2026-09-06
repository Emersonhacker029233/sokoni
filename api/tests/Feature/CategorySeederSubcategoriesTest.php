<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subcategory taxonomy (mega menu / search sidebar feature) — the
 * categories table already supported two levels via parent_id, but no
 * subcategories existed until now.
 */
class CategorySeederSubcategoriesTest extends TestCase
{
    use RefreshDatabase;

    /** Every parent gets a real subcategory tree except "Other" — a catch-all has nothing to subdivide into. */
    public function test_every_parent_category_except_other_gets_subcategories(): void
    {
        (new CategorySeeder)->run();

        $parents = Category::whereNull('parent_id')->get();
        $this->assertGreaterThanOrEqual(13, $parents->count());

        foreach ($parents as $parent) {
            $childCount = Category::where('parent_id', $parent->id)->count();

            if ($parent->name_en === 'Other') {
                $this->assertSame(0, $childCount, 'Other should have no subcategories');

                continue;
            }

            $this->assertGreaterThanOrEqual(4, $childCount, "{$parent->name_en} should have at least 4 subcategories");
            $this->assertLessThanOrEqual(8, $childCount, "{$parent->name_en} should have at most 8 subcategories");
        }
    }

    public function test_subcategories_have_both_english_and_swahili_names(): void
    {
        (new CategorySeeder)->run();

        $children = Category::whereNotNull('parent_id')->get();
        $this->assertGreaterThan(0, $children->count());

        foreach ($children as $child) {
            $this->assertNotEmpty($child->name_en);
            $this->assertNotEmpty($child->name_sw);
        }
    }

    public function test_running_the_seeder_twice_does_not_duplicate_subcategories(): void
    {
        (new CategorySeeder)->run();
        $firstRunCount = Category::whereNotNull('parent_id')->count();

        (new CategorySeeder)->run();
        $secondRunCount = Category::whereNotNull('parent_id')->count();

        $this->assertSame($firstRunCount, $secondRunCount);
    }

    public function test_a_subcategory_slug_resolves_under_its_parent(): void
    {
        (new CategorySeeder)->run();

        $response = $this->get('/c/phones-accessories/smartphones');

        $response->assertOk();
    }

    /** The whole point of a two-level FK: browsing the parent must include products tagged with a child. */
    public function test_browsing_the_parent_category_includes_products_tagged_with_a_subcategory(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['category_id' => $tvs->id, 'seller_id' => $seller->id]);

        $response = $this->get('/c/electronics');

        $response->assertOk();
        $response->assertSee($product->title);
    }

    /** A child category page stays narrow — it must NOT show products from a sibling subcategory. */
    public function test_browsing_a_subcategory_excludes_products_from_a_sibling_subcategory(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();
        $audio = Category::where('parent_id', $electronics->id)->where('name_en', 'Audio')->firstOrFail();
        $seller = SellerProfile::factory()->verified()->create();
        $tvProduct = Product::factory()->create(['category_id' => $tvs->id, 'seller_id' => $seller->id, 'title' => 'Samsung Smart TV 43 inch']);
        $audioProduct = Product::factory()->create(['category_id' => $audio->id, 'seller_id' => $seller->id, 'title' => 'JBL Bluetooth Speaker']);

        $response = $this->get('/c/electronics/tvs');

        $response->assertOk();
        $response->assertSee($tvProduct->title);
        $response->assertDontSee($audioProduct->title);
    }
}

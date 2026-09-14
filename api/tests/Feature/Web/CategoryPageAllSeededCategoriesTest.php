<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Catalog\CategoryCatalogService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The route sweep in AllWebRoutesAvoidServerErrorsTest only exercises one
 * category. This runs the real production category set (CategorySeeder's
 * exact 10 rows — same icons, same names) through the real route, plus the
 * two specific production-vs-local divergences worth checking per the
 * incident: a category that actually has children (nothing seeds these
 * locally — only the admin panel creates them, so a fresh local seed can
 * never reproduce a "category with children" 500 without deliberately
 * building one, as this test does), and a category with a live sponsored
 * product (the one code path — Product::scopeSponsoredActive() — that the
 * home page never exercises but every category page does on every load).
 */
class CategoryPageAllSeededCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_real_seeded_category_page_returns_200(): void
    {
        $this->seed(CategorySeeder::class);

        $catalog = app(CategoryCatalogService::class);
        $failures = [];

        foreach (Category::whereNull('parent_id')->get() as $category) {
            $slug = $catalog->slug($category);
            $response = $this->get('/c/'.$slug);

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$category->name_en} ({$slug}) => {$response->getStatusCode()}";
            }
        }

        $this->assertEmpty($failures, "Non-200 responses:\n".implode("\n", $failures));
    }

    public function test_a_category_with_a_real_child_subcategory_returns_200(): void
    {
        $this->seed(CategorySeeder::class);
        $catalog = app(CategoryCatalogService::class);

        // Food & Groceries's own real "Restaurant" subcategory (Part 2,
        // client feedback) — no need to fabricate one.
        $parent = Category::where('name_en', 'Food & Groceries')->whereNull('parent_id')->firstOrFail();
        $child = Category::where('parent_id', $parent->id)->where('name_en', 'Restaurant')->firstOrFail();

        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $child->id]);

        // Both the parent (sidebar should list the child) and the child itself.
        $this->get('/c/'.$catalog->slug($parent))->assertOk();
        $this->get('/c/'.$catalog->slug($parent).'/'.$catalog->slug($child))->assertOk();
    }

    public function test_a_category_page_with_a_live_sponsored_product_returns_200(): void
    {
        $this->seed(CategorySeeder::class);
        $catalog = app(CategoryCatalogService::class);

        $category = Category::where('name_en', 'Food & Groceries')->whereNull('parent_id')->firstOrFail();
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'is_sponsored' => true,
            'sponsored_until' => now()->addDay(),
        ]);

        $this->get('/c/'.$catalog->slug($category))->assertOk();
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tester feedback C3: a category + subcategory filter in the search
 * sidebar, expandable, single-select, with counts reflecting the current
 * result set (every other active filter still applies).
 */
class SearchCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_shows_every_top_level_category_with_a_real_count(): void
    {
        $electronics = Category::factory()->create(['name_en' => 'Electronics']);
        $fashion = Category::factory()->create(['name_en' => 'Fashion']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->count(2)->create(['seller_id' => $seller->id, 'category_id' => $electronics->id]);
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $fashion->id]);

        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee('Electronics');
        $response->assertSee('Fashion');
    }

    public function test_selecting_a_category_filters_results_to_only_that_category(): void
    {
        $electronics = Category::factory()->create(['name_en' => 'Electronics']);
        $fashion = Category::factory()->create(['name_en' => 'Fashion']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $electronics->id, 'title' => 'A Real Phone']);
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $fashion->id, 'title' => 'A Real Shirt']);

        $response = $this->get("/search?category_id={$electronics->id}");

        $response->assertOk();
        $response->assertSee('A Real Phone');
        $response->assertDontSee('A Real Shirt');
    }

    public function test_a_top_level_categorys_count_includes_its_childrens_products(): void
    {
        $parent = Category::factory()->create(['name_en' => 'Vehicles & Parts']);
        $child = Category::factory()->create(['name_en' => 'Motorbike Parts', 'parent_id' => $parent->id]);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $parent->id]);
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $child->id]);

        $response = $this->get('/search');

        $response->assertOk();
        // Both the parent's own product and its child's product count
        // toward the parent's total shown in the sidebar.
        $response->assertSeeInOrder(['Vehicles & Parts', '2']);
    }

    public function test_expanding_a_category_shows_its_subcategories_with_their_own_counts(): void
    {
        $parent = Category::factory()->create(['name_en' => 'Vehicles & Parts']);
        $child = Category::factory()->create(['name_en' => 'Motorbike Parts', 'parent_id' => $parent->id]);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $child->id]);

        $response = $this->get("/search?category_id={$parent->id}");

        $response->assertOk();
        $response->assertSee('Motorbike Parts');
    }

    public function test_category_counts_respect_other_active_filters(): void
    {
        $electronics = Category::factory()->create(['name_en' => 'Electronics']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $electronics->id, 'price' => 5000]);
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $electronics->id, 'price' => 500000]);

        $response = $this->get('/search?price_max=10000');

        $response->assertOk();
        // Only 1 of Electronics' 2 products matches the active price
        // filter — the count in the sidebar must reflect that, not the
        // category's total.
        $response->assertSeeInOrder(['Electronics', '1']);
    }
}

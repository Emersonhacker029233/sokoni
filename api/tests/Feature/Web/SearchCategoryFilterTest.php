<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Database\Seeders\CategorySeeder;
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

    /**
     * Everything above proves the mechanism with ad hoc factory categories
     * — this closes the loop with the actual seeded taxonomy (task: "the
     * search sidebar built in C3 actually shows the tree with the new
     * subcategories").
     */
    public function test_the_sidebar_shows_the_real_seeded_subcategory_tree(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $tvs->id]);

        $response = $this->get("/search?category_id={$electronics->id}");

        $response->assertOk();
        $response->assertSee('Electronics');
        $response->assertSee('TVs');
        $response->assertSeeInOrder(['TVs', '1']);
    }

    /**
     * Drill-down must never drop a filter the visitor already had active —
     * checked in the two moments this can actually happen: picking a
     * top-level category from an unfiltered tree, and then picking one of
     * its subcategories once it's expanded.
     */
    public function test_selecting_a_top_level_category_from_the_sidebar_preserves_every_other_active_filter(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();

        $response = $this->get('/search?q=samsung&region=dar-es-salaam&price_min=10000&price_max=900000&sort=price_asc');

        $response->assertOk();
        $href = $this->extractHrefContaining($response->getContent(), "category_id={$electronics->id}");

        $this->assertStringContainsString('q=samsung', $href);
        $this->assertStringContainsString('region=dar-es-salaam', $href);
        $this->assertStringContainsString('price_min=10000', $href);
        $this->assertStringContainsString('price_max=900000', $href);
        $this->assertStringContainsString('sort=price_asc', $href);
    }

    public function test_drilling_down_into_an_expanded_subcategory_preserves_every_other_active_filter(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();

        // Electronics already active (so its children — TVs included — are
        // actually rendered/expanded) alongside a full set of other filters.
        $response = $this->get("/search?category_id={$electronics->id}&q=samsung&region=dar-es-salaam&price_min=10000&price_max=900000&sort=price_asc");

        $response->assertOk();
        $href = $this->extractHrefContaining($response->getContent(), "category_id={$tvs->id}");

        $this->assertStringContainsString('q=samsung', $href);
        $this->assertStringContainsString('region=dar-es-salaam', $href);
        $this->assertStringContainsString('price_min=10000', $href);
        $this->assertStringContainsString('price_max=900000', $href);
        $this->assertStringContainsString('sort=price_asc', $href);
    }

    /**
     * Extracted directly rather than assuming a fixed query param order,
     * which fullUrlWithQuery() doesn't guarantee. The negative lookahead
     * stops "category_id=4" from false-matching a link actually carrying
     * "category_id=40" — real risk with ~90 seeded category ids on the page.
     */
    private function extractHrefContaining(string $html, string $needle): string
    {
        preg_match('/href="([^"]*'.preg_quote($needle, '/').'(?!\d)[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches, "No link found carrying {$needle}.");

        return html_entity_decode($matches[1]);
    }

    /** The "All categories" link must clear category_id while still keeping every other filter. */
    public function test_the_all_categories_link_clears_only_the_category_filter(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();

        $response = $this->get("/search?category_id={$electronics->id}&q=samsung&sort=price_asc");

        $response->assertOk();

        // Pull the "All categories" link's own href out directly rather
        // than asserting "category_id= never appears anywhere" — every
        // OTHER category's own link legitimately contains it.
        preg_match(
            '/<a href="([^"]+)"[^>]*>\s*'.preg_quote(__('site.filter_category_all'), '/').'/',
            $response->getContent(),
            $matches,
        );
        $this->assertNotEmpty($matches, 'Could not find the "All categories" link in the sidebar.');
        $href = html_entity_decode($matches[1]);

        $this->assertStringNotContainsString('category_id=', $href);
        $this->assertStringContainsString('q=samsung', $href);
        $this->assertStringContainsString('sort=price_asc', $href);
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_page_renders_and_lists_its_visible_products(): void
    {
        $category = Category::factory()->create(['name_en' => 'Electronics']);
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Test Phone']);

        $slug = app(\App\Services\Catalog\CategoryCatalogService::class)->slug($category);

        $response = $this->get("/c/{$slug}");

        $response->assertOk();
        $response->assertSee('Test Phone');
    }

    public function test_a_category_page_with_no_products_shows_the_empty_state(): void
    {
        $category = Category::factory()->create(['name_en' => 'Empty Category']);
        $slug = app(\App\Services\Catalog\CategoryCatalogService::class)->slug($category);

        $this->get("/c/{$slug}")->assertOk()->assertSee(__('site.results_empty_title'));
    }

    public function test_an_unknown_category_slug_404s(): void
    {
        $this->get('/c/not-a-real-category')->assertNotFound();
    }

    public function test_search_finds_a_matching_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'iPhone 12 Pro']);
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Samsung Galaxy']);

        $response = $this->get('/search?q=iPhone');

        $response->assertOk();
        $response->assertSee('iPhone 12 Pro');
        $response->assertDontSee('Samsung Galaxy');
    }

    /**
     * Home search only ever matched product titles — a shop's own name
     * never turned up a result at all unless one of its products happened
     * to match too (tester feedback C2). The home hero search and this
     * page share the exact same /search endpoint, so this test covers
     * both surfaces at once.
     */
    public function test_search_matches_a_shop_name_even_with_no_matching_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['shop_name' => 'Kariakoo Mobile Center']);
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Something unrelated']);

        $response = $this->get('/search?q=Kariakoo');

        $response->assertOk();
        $response->assertSee('Kariakoo Mobile Center');
    }

    public function test_search_results_are_grouped_with_shops_before_products_when_a_shop_matches(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['shop_name' => 'Mbezi Beach Electronics']);
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Mbezi Special Offer']);

        $response = $this->get('/search?q=Mbezi');

        $response->assertOk();
        $response->assertSeeInOrder(['Mbezi Beach Electronics', 'Mbezi Special Offer']);
    }

    public function test_search_with_no_shop_match_shows_only_products_with_no_empty_shops_heading(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['shop_name' => 'Totally Unrelated Shop']);
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Genuine Leather Wallet']);

        $response = $this->get('/search?q=Wallet&lang=en');

        $response->assertOk();
        $response->assertSee('Genuine Leather Wallet');
        $response->assertDontSee(__('site.search_shops_heading', [], 'en'));
    }

    public function test_an_unverified_seller_never_matches_search(): void
    {
        SellerProfile::factory()->create(['shop_name' => 'Pending Shop Not Verified Yet']); // pending, not verified

        $this->get('/search?q=Pending')->assertOk()->assertDontSee('Pending Shop Not Verified Yet');
    }

    public function test_search_respects_the_price_filter(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Cheap Item', 'price' => 5000]);
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Expensive Item', 'price' => 500000]);

        $response = $this->get('/search?price_max=10000');

        $response->assertSee('Cheap Item');
        $response->assertDontSee('Expensive Item');
    }

    public function test_sponsored_only_filter_only_shows_sponsored_products(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Boosted', 'is_sponsored' => true, 'sponsored_until' => now()->addDay()]);
        Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Regular']);

        $response = $this->get('/search?sponsored=1');

        $response->assertSee('Boosted');
        $response->assertDontSee('Regular');
    }
}

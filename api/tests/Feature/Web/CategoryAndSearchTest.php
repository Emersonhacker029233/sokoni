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

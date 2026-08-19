<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sitemap_index_lists_the_three_child_sitemaps(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml');
        $response->assertSee('sitemap-products.xml', false);
        $response->assertSee('sitemap-shops.xml', false);
        $response->assertSee('sitemap-categories.xml', false);
    }

    public function test_the_products_sitemap_lists_only_visible_products(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $visible = Product::factory()->create(['seller_id' => $seller->id, 'is_active' => true, 'is_hidden' => false]);
        $hidden = Product::factory()->create(['seller_id' => $seller->id, 'is_active' => true, 'is_hidden' => true]);

        $response = $this->get('/sitemap-products.xml');

        $response->assertOk();
        $response->assertSee('/p/'.$visible->id, false);
        $response->assertDontSee('/p/'.$hidden->id, false);
    }

    public function test_the_shops_sitemap_lists_only_verified_shops(): void
    {
        $verified = SellerProfile::factory()->verified()->create(['handle' => 'verified-shop']);
        $pending = SellerProfile::factory()->create(['handle' => 'pending-shop', 'status' => 'pending']);

        $response = $this->get('/sitemap-shops.xml');

        $response->assertOk();
        $response->assertSee('/@verified-shop', false);
        $response->assertDontSee('/@pending-shop', false);
    }

    public function test_the_categories_sitemap_lists_active_categories(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->get('/sitemap-categories.xml');

        $response->assertOk();
        $response->assertSee('/c/', false);
    }

    public function test_robots_txt_points_at_the_sitemap_index(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=utf-8');
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
        $response->assertSee('Disallow: /account', false);
    }
}

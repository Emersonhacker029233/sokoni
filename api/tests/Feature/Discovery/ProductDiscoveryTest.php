<?php

namespace Tests\Feature\Discovery;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    /** Dar es Salaam city centre, used as the search origin in these tests. */
    private const ORIGIN_LAT = -6.7924;
    private const ORIGIN_LNG = 39.2083;

    public function test_radius_filters_out_far_sellers(): void
    {
        $near = SellerProfile::factory()->verified()->create(['lat' => -6.80, 'lng' => 39.21]); // ~1km away
        $far = SellerProfile::factory()->verified()->create(['lat' => -7.50, 'lng' => 39.80]); // ~90km+ away

        $nearProduct = Product::factory()->create(['seller_id' => $near->id]);
        $farProduct = Product::factory()->create(['seller_id' => $far->id]);

        $response = $this->getJson('/api/products?lat='.self::ORIGIN_LAT.'&lng='.self::ORIGIN_LNG.'&radius_km=5')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($nearProduct->id));
        $this->assertFalse($ids->contains($farProduct->id));
    }

    public function test_results_are_sorted_nearest_first_by_default(): void
    {
        $far = SellerProfile::factory()->verified()->create(['lat' => -6.85, 'lng' => 39.25]);
        $near = SellerProfile::factory()->verified()->create(['lat' => -6.795, 'lng' => 39.21]);

        Product::factory()->create(['seller_id' => $far->id, 'title' => 'Far product']);
        Product::factory()->create(['seller_id' => $near->id, 'title' => 'Near product']);

        $response = $this->getJson('/api/products?lat='.self::ORIGIN_LAT.'&lng='.self::ORIGIN_LNG)->assertOk();

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertEquals('Near product', $titles->first());
    }

    public function test_category_filter(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $electronics = Category::factory()->create();
        $fashion = Category::factory()->create();

        $matching = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $electronics->id]);
        $other = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $fashion->id]);

        $response = $this->getJson("/api/products?category_id={$electronics->id}")->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($matching->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_search_matches_title(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $match = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Samsung Galaxy A54']);
        $noMatch = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Wooden dining table']);

        $response = $this->getJson('/api/products?q=Samsung')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($match->id));
        $this->assertFalse($ids->contains($noMatch->id));
    }

    public function test_trending_sorts_by_views_descending(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $popular = Product::factory()->create(['seller_id' => $seller->id, 'views' => 500]);
        $unpopular = Product::factory()->create(['seller_id' => $seller->id, 'views' => 2]);

        $response = $this->getJson('/api/products?sort=trending')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->search($popular->id) < $ids->search($unpopular->id));
    }

    public function test_pagination_is_twenty_per_page(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory(25)->create(['seller_id' => $seller->id]);

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}

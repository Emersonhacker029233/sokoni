<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_renders(): void
    {
        Category::factory()->create(['name_en' => 'Electronics']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sokoni', false);
    }

    public function test_the_home_page_renders_with_no_data_at_all(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * B2 (tester feedback): "Near you" is the first product grid on the
     * page — its first row sits at/near the fold, so lazy-loading it like
     * every other card just defers the very photos a visitor sees first.
     * The first 4 get `loading="eager"` + `fetchpriority="high"`; the 5th
     * (and up) stay lazy, same as any other section.
     */
    public function test_the_first_four_near_you_cards_are_eager_loaded_and_the_rest_stay_lazy(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        // Dar es Salaam-jittered coordinates by default (SellerProfileFactory) —
        // within HomeController::nearYou()'s 25km radius of DarEsSalaam::LAT/LNG.
        $products = Product::factory()->count(5)->create(['seller_id' => $seller->id]);
        foreach ($products as $product) {
            \App\Models\ProductMedia::factory()->create(['product_id' => $product->id]);
        }

        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();

        $eagerCount = substr_count($html, 'loading="eager"');
        $highPriorityCount = substr_count($html, 'fetchpriority="high"');
        // Exactly 4 — not "at least 4" — since a wrong `>=` off-by-one
        // fix could otherwise silently make every card eager and this
        // assertion would still pass.
        $this->assertSame(4, $eagerCount);
        $this->assertSame(4, $highPriorityCount);
    }

    /**
     * Guards specifically against the Part 2 "nothing renders below the
     * hero" report: proves every below-the-fold section — latest listings,
     * featured shops, a live offer, the app-download band — actually
     * contains real, distinguishable content in the response body, not
     * just that the page returns 200 with an empty/collapsed layout.
     */
    public function test_every_section_below_the_hero_renders_real_content(): void
    {
        config(['sokoni.app_links' => ['google_play' => 'https://play.google.com/store/apps/details?id=tz.co.sokoni']]);

        $category = Category::factory()->create();
        $seller = SellerProfile::factory()->verified()->create(['rating_count' => 10, 'shop_name' => 'Amina Electronics']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'A genuinely unique latest-listing title',
        ]);
        Offer::factory()->create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('A genuinely unique latest-listing title');
        $response->assertSee('Amina Electronics');
        $response->assertSee(__('site.home_app_banner_title'));
    }

    /**
     * Reproduces the real bug directly rather than just checking distinct
     * timestamps sort correctly (which the old, unfixed query already got
     * right): a bulk-seeded/imported batch of products routinely shares
     * the exact same created_at *second* (timestamps() has no fractional
     * precision), and `latest()` alone has no secondary tiebreaker for
     * ties — which in practice resolved to ascending id, the oldest of
     * the tied batch first. `id` is monotonically increasing with
     * insertion order, so it's what actually determines "newest" once
     * created_at ties.
     */
    public function test_latest_listings_shows_the_most_recently_created_product_first_even_when_timestamps_tie(): void
    {
        // No lat/lng: keeps these products out of the separate, earlier
        // "Near You" section (sorted by distance, not recency), which
        // would otherwise render them in whatever order distance gives
        // and mask a real ordering bug in "Latest listings" below it.
        $seller = SellerProfile::factory()->verified()->create(['lat' => null, 'lng' => null]);
        $tiedTimestamp = now()->subDay();

        Product::factory()->create([
            'seller_id' => $seller->id,
            'title' => 'An older tied-timestamp product',
            'created_at' => $tiedTimestamp,
            'updated_at' => $tiedTimestamp,
        ]);

        // Inserted after the one above despite sharing an identical
        // created_at second — the only thing that still distinguishes it
        // as genuinely newer is its higher id.
        Product::factory()->create([
            'seller_id' => $seller->id,
            'title' => 'The genuinely newest tied-timestamp product',
            'created_at' => $tiedTimestamp,
            'updated_at' => $tiedTimestamp,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder([
            'The genuinely newest tied-timestamp product',
            'An older tied-timestamp product',
        ]);
    }

    public function test_a_category_with_zero_visible_products_is_hidden_from_the_browse_categories_section(): void
    {
        $categoryWithNoProducts = Category::factory()->create(['name_en' => 'Agriculture']);
        $categoryWithProducts = Category::factory()->create(['name_en' => 'Electronics']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $categoryWithProducts->id]);

        $response = $this->get('/');

        $response->assertOk();
        $categories = $response->viewData('categories');

        $this->assertTrue($categories->contains('id', $categoryWithProducts->id));
        $this->assertFalse($categories->contains('id', $categoryWithNoProducts->id));
    }

    /**
     * Even a category that does have products must never show the raw
     * count on this section — tester feedback: "Electronics (5)" reads as
     * a weakness, not information. The category *landing* page is where
     * counts stay meaningful (a visitor has already committed to it).
     */
    /**
     * Client feedback: "Ensure Services appears in the home category
     * buttons." Audited first rather than guessed at: Services already had
     * real products and no artificial limit hid it in the current code —
     * this locks that in as a guarantee rather than leaving it to
     * accidentally regress the next time the zero-count filter (or a
     * future "top N categories" change) is touched.
     */
    public function test_services_category_always_appears_on_the_home_page_when_it_has_products(): void
    {
        $services = \App\Models\Category::factory()->create(['name_en' => 'Services']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $services->id]);

        $response = $this->get('/');

        $response->assertOk();
        $categories = $response->viewData('categories');
        $this->assertTrue($categories->contains('id', $services->id));
    }

    public function test_the_browse_categories_section_never_shows_a_raw_product_count(): void
    {
        $category = Category::factory()->create(['name_en' => 'Electronics']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->count(3)->create(['seller_id' => $seller->id, 'category_id' => $category->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Electronics');
        // The old markup rendered the raw count in its own span right after
        // the category name — asserts that exact node is gone, rather than
        // asserting the bare digit "3" is absent, which could coincidentally
        // appear elsewhere on the page for unrelated reasons.
        $response->assertDontSee('text-sokoni-black/40">3</span>', false);
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A2 (tester feedback: "product photos still flickering", re-diagnosed
 * from scratch since the earlier x-cloak fix — a PDP-gallery-only change —
 * didn't touch this). Root cause: <x-product-card>'s image wrapper carried
 * the shared `.skeleton` class (an infinite CSS `animate-pulse`
 * animation) permanently, with nothing anywhere that ever removed it once
 * the real photo had loaded — a continuously-running pulse compositing
 * underneath every loaded product photo, on every page that renders a
 * card, showing through at the rounded corners as a persistent flicker.
 */
class ProductCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_card_with_a_loaded_photo_never_carries_the_permanent_pulse_animation(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        ProductMedia::factory()->create(['product_id' => $product->id]);

        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee($product->title);
        $response->assertDontSee('skeleton', false);
        $response->assertDontSee('animate-pulse', false);
    }

    public function test_a_lazy_card_never_gets_a_high_fetch_priority_hint(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        ProductMedia::factory()->create(['product_id' => $product->id]);

        $response = $this->get('/search');

        $response->assertOk();
        $response->assertDontSee('fetchpriority', false);
    }
}

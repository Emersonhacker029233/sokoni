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

    /**
     * B8 (tester feedback: "tick reads clearly in app but not web"). The
     * app's Icons.verified_rounded is one filled glyph with no circular
     * wrapper — the checkmark is a cutout in that same shape, not a second
     * icon drawn on top of a badge-shaped background. The web's previous
     * markup used a completely different, checkmark-less outline path
     * inside a solid circle, so no check ever actually rendered. This
     * pins the real Material glyph path (verified, rounded, filled) in
     * place and guards against the old broken path or circle wrapper
     * silently coming back.
     */
    public function test_a_verified_sellers_product_card_shows_the_real_verified_glyph_with_no_circular_wrapper(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        ProductMedia::factory()->create(['product_id' => $product->id]);

        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee('M438-452-58-57q', false);
        $response->assertDontSee('M10 1l2.39 1.36L15 2', false);
        $response->assertDontSee('bg-sokoni-yellow text-white', false);
    }

    /**
     * Part A (client feedback): "images inconsistently missing... add a
     * visible placeholder so a failed image never renders as blank
     * space." There was no `onerror` handling at all before this — a
     * 404/stale-host photo just left whatever the browser's own default
     * broken-image rendering is. Pins the fallback markup in place rather
     * than just "the image tag exists."
     */
    public function test_a_product_cards_photo_falls_back_to_a_visible_placeholder_on_a_failed_load(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        ProductMedia::factory()->create(['product_id' => $product->id]);

        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee("onerror=\"this.style.display='none'; this.nextElementSibling.style.display='flex'\"", false);
    }
}

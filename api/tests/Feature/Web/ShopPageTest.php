<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_page_renders_with_local_business_schema(): void
    {
        $seller = SellerProfile::factory()->verified()->create([
            'shop_name' => 'Amina Electronics',
            'opening_hours' => ['monday' => ['open' => '08:00', 'close' => '18:00'], 'sunday' => null],
        ]);
        Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->get("/@{$seller->handle}");

        $response->assertOk();
        $response->assertSee('Amina Electronics');
        $response->assertSee('"@type":"LocalBusiness"', false);
        $response->assertSee('08:00');
    }

    /**
     * Real production bug (tester feedback A1): the header printed the
     * literal text "{{ $seller->handle }}" instead of the actual handle —
     * `@{{ }}` is Blade's escape for a literal `{{ }}` in the rendered
     * output (for JS frameworks sharing that delimiter), not a way to
     * prefix an expression with "@". Asserts the real handle renders with
     * its "@" prefix, and that the broken literal text never does.
     */
    public function test_the_handle_renders_with_its_at_prefix_not_as_literal_blade_syntax(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['handle' => 'amina_electronics']);

        $response = $this->get("/@{$seller->handle}");

        $response->assertOk();
        $response->assertSee('@amina_electronics', false);
        $response->assertDontSee('{{ $seller->handle }}', false);
    }

    public function test_an_unverified_seller_shop_page_404s(): void
    {
        $seller = SellerProfile::factory()->create(); // pending, not verified

        $this->get("/@{$seller->handle}")->assertNotFound();
    }

    public function test_the_gallery_tab_renders(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->get("/@{$seller->handle}?tab=gallery")->assertOk();
    }

    public function test_the_reviews_tab_renders(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->get("/@{$seller->handle}?tab=reviews")->assertOk();
    }

    /**
     * A1 (tester feedback): "buyer cannot leave a review", reported from
     * this exact tab. The gate itself (order-completed, not-already-
     * reviewed) is correct and stays — the bug was this tab giving zero
     * indication either way, reading as broken for every buyer regardless
     * of eligibility. These three cover each real state.
     */
    public function test_a_buyer_with_a_completed_unreviewed_order_sees_a_link_to_review_it(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'completed']);

        $response = $this->actingAsWebUser($buyer)->get("/@{$seller->handle}?tab=reviews");

        $response->assertOk();
        $response->assertSee(__('site.shop_reviewable_prompt'));
        $response->assertSee(route('web.account.orders.show', $order), false);
    }

    public function test_a_buyer_with_no_completed_order_sees_the_gate_explanation_not_a_blank_tab(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $buyer = User::factory()->create();

        $response = $this->actingAsWebUser($buyer)->get("/@{$seller->handle}?tab=reviews");

        $response->assertOk();
        $response->assertSee(__('site.shop_review_gate_explanation'));
    }

    public function test_a_buyer_who_already_reviewed_their_completed_order_sees_the_gate_explanation_not_a_stale_link(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'completed']);
        \App\Models\Review::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id]);

        $response = $this->actingAsWebUser($buyer)->get("/@{$seller->handle}?tab=reviews");

        $response->assertOk();
        $response->assertSee(__('site.shop_review_gate_explanation'));
        $response->assertDontSee(__('site.shop_reviewable_prompt'));
    }

    public function test_a_signed_out_visitor_sees_the_gate_explanation_not_a_blank_tab(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $response = $this->get("/@{$seller->handle}?tab=reviews");

        $response->assertOk();
        $response->assertSee(__('site.shop_review_gate_explanation'));
    }

    public function test_the_full_address_and_member_since_year_render_in_the_header(): void
    {
        $seller = SellerProfile::factory()->verified()->create([
            'address' => 'Mlimani City, Sam Nujoma Road',
            'district' => 'Kinondoni',
            'region' => 'Dar es Salaam',
            'created_at' => '2024-03-01',
        ]);

        $response = $this->get("/@{$seller->handle}");

        $response->assertOk();
        $response->assertSee('Mlimani City, Sam Nujoma Road');
        $response->assertSee('Kinondoni');
        $response->assertSee('2024');
    }

    public function test_a_shop_with_coordinates_shows_a_lazy_loaded_openstreetmap_embed_and_directions_links(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['lat' => -6.7924, 'lng' => 39.2083]);

        $response = $this->get("/@{$seller->handle}");

        $response->assertOk();
        $response->assertSee('openstreetmap.org/export/embed.html', false);
        $response->assertSee('loading="lazy"', false);
        // lat/lng are decimal:7-cast, so they render as e.g. "-6.7924000", not the bare "-6.7924" the test set — a fixed-precision string, not a bug.
        $response->assertSee('geo:-6.7924', false);
        $response->assertSee('openstreetmap.org/directions', false);
    }

    public function test_a_shop_with_no_coordinates_shows_no_map_frame_at_all(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['lat' => null, 'lng' => null]);

        $response = $this->get("/@{$seller->handle}");

        $response->assertOk();
        $response->assertDontSee('<iframe', false);
        $response->assertDontSee('openstreetmap.org/export/embed.html', false);
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_page_renders_with_schema_org_markup(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'iPhone 12 Pro 64GB']);

        $slug = Str::slug($product->title);
        $response = $this->get("/p/{$product->id}/{$slug}");

        $response->assertOk();
        $response->assertSee('iPhone 12 Pro 64GB');
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('og:title', false);
        $response->assertSee('twitter:card', false);
    }

    public function test_a_wrong_slug_redirects_to_the_canonical_url(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'iPhone 12 Pro 64GB']);

        $response = $this->get("/p/{$product->id}/wrong-slug");

        $response->assertRedirect("/p/{$product->id}/".Str::slug($product->title));
    }

    public function test_a_hidden_product_404s(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_hidden' => true]);

        $this->get("/p/{$product->id}/".Str::slug($product->title))->assertNotFound();
    }

    public function test_viewing_a_product_increments_its_view_count(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'views' => 0]);

        $this->get("/p/{$product->id}/".Str::slug($product->title));

        $this->assertSame(1, $product->fresh()->views);
    }

    public function test_reveal_call_logs_a_lead(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->postJson(route('web.product.reveal-call', $product->id))->assertOk();

        $this->assertDatabaseHas('leads', ['product_id' => $product->id, 'seller_id' => $seller->id, 'type' => 'call']);
    }

    public function test_a_review_with_a_seller_reply_shows_both(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $order = Order::factory()->create(['seller_id' => $seller->id]);
        Review::factory()->create([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'comment' => 'Great seller!',
            'reply' => 'Thank you for shopping with us.',
            'replied_at' => now(),
        ]);

        $response = $this->get("/p/{$product->id}/".Str::slug($product->title));

        $response->assertSee('Great seller!');
        $response->assertSee('Thank you for shopping with us.');
    }
}

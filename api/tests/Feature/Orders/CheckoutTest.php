<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_computes_totals_server_side_from_actual_prices(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();
        $productA = Product::factory()->create(['seller_id' => $seller->id, 'price' => 10000]);
        $productB = Product::factory()->create(['seller_id' => $seller->id, 'price' => 5000]);

        $response = $this->actingAs($buyer)->postJson('/api/orders', [
            // Client-sent price would be ignored even if present — the
            // request doesn't accept one at all, only product_id/qty.
            'items' => [
                ['product_id' => $productA->id, 'qty' => 2],
                ['product_id' => $productB->id, 'qty' => 1],
            ],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertCreated();

        $response->assertJsonPath('data.subtotal', 25000);
        $response->assertJsonPath('data.total', 25000);
        $this->assertDatabaseHas('orders', ['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'subtotal' => 25000]);
    }

    public function test_delivery_orders_get_a_delivery_fee(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 10000]);

        $response = $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'delivery',
            'address' => '123 Uhuru Street',
            'payment_method' => 'cash_on_delivery',
        ])->assertCreated();

        $this->assertGreaterThan(10000, $response->json('data.total'));
    }

    public function test_cart_cannot_mix_two_sellers(): void
    {
        $buyer = User::factory()->create();
        $sellerA = SellerProfile::factory()->verified()->create();
        $sellerB = SellerProfile::factory()->verified()->create();
        $productA = Product::factory()->create(['seller_id' => $sellerA->id]);
        $productB = Product::factory()->create(['seller_id' => $sellerB->id]);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [
                ['product_id' => $productA->id, 'qty' => 1],
                ['product_id' => $productB->id, 'qty' => 1],
            ],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertStatus(422);
    }

    public function test_order_opens_a_conversation_automatically(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertCreated();

        $this->assertDatabaseHas('conversations', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_id' => $response->json('data.id'),
        ]);
        // The app opens this thread automatically right after checkout
        // (CLAUDE.md feature 8), so the order response needs the id directly.
        $this->assertNotNull($response->json('data.conversation_id'));
    }

    public function test_only_the_seller_can_advance_order_status(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($order->buyer)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertForbidden();

        $this->actingAs($order->seller->user)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_cannot_skip_a_status(): void
    {
        $order = Order::factory()->create(); // pending

        $this->actingAs($order->seller->user)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertStatus(422);
    }

    public function test_either_party_can_cancel_a_pending_order(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($order->buyer)
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'Changed my mind'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_stranger_cannot_view_someone_elses_order(): void
    {
        $order = Order::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson("/api/orders/{$order->id}")->assertForbidden();
    }
}

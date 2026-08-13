<?php

namespace Tests\Feature\Reviews;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The entire defence against fake reviews (CLAUDE.md feature 2):
     * a completed order from that seller is required, enforced server-side.
     */
    public function test_cannot_review_without_a_completed_order(): void
    {
        $order = Order::factory()->create(); // status: pending

        $this->actingAs($order->buyer)
            ->postJson("/api/orders/{$order->id}/review", ['rating' => 5])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_cannot_review_someone_elses_order(): void
    {
        $order = Order::factory()->completed()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/orders/{$order->id}/review", ['rating' => 5])
            ->assertForbidden();
    }

    public function test_buyer_with_completed_order_can_review(): void
    {
        $order = Order::factory()->completed()->create();

        $response = $this->actingAs($order->buyer)
            ->postJson("/api/orders/{$order->id}/review", ['rating' => 5, 'comment' => 'Excellent!'])
            ->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'buyer_id' => $order->buyer_id,
            'rating' => 5,
        ]);
    }

    public function test_cannot_review_the_same_order_twice(): void
    {
        $order = Order::factory()->completed()->create();

        $this->actingAs($order->buyer)
            ->postJson("/api/orders/{$order->id}/review", ['rating' => 5])
            ->assertStatus(201);

        $this->actingAs($order->buyer)
            ->postJson("/api/orders/{$order->id}/review", ['rating' => 1])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_review_updates_seller_denormalised_rating(): void
    {
        $orderA = Order::factory()->completed()->create();
        $seller = $orderA->seller;
        $orderB = Order::factory()->completed()->create(['seller_id' => $seller->id]);

        Review::factory()->create(['order_id' => $orderA->id, 'seller_id' => $seller->id, 'buyer_id' => $orderA->buyer_id, 'rating' => 5]);
        Review::factory()->create(['order_id' => $orderB->id, 'seller_id' => $seller->id, 'buyer_id' => $orderB->buyer_id, 'rating' => 3]);

        $seller->refresh();
        $this->assertEquals(4.0, (float) $seller->rating_avg);
        $this->assertEquals(2, $seller->rating_count);
    }

    public function test_seller_can_reply_once(): void
    {
        $order = Order::factory()->completed()->create();
        $review = Review::factory()->create([
            'order_id' => $order->id, 'seller_id' => $order->seller_id, 'buyer_id' => $order->buyer_id,
        ]);

        $this->actingAs($order->seller->user)
            ->patchJson("/api/reviews/{$review->id}/reply", ['reply' => 'Thank you!'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Thank you!');

        $this->actingAs($order->seller->user)
            ->patchJson("/api/reviews/{$review->id}/reply", ['reply' => 'Again'])
            ->assertStatus(422);
    }

    public function test_only_the_seller_can_reply(): void
    {
        $order = Order::factory()->completed()->create();
        $review = Review::factory()->create([
            'order_id' => $order->id, 'seller_id' => $order->seller_id, 'buyer_id' => $order->buyer_id,
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patchJson("/api/reviews/{$review->id}/reply", ['reply' => 'Not mine to reply to'])
            ->assertForbidden();
    }
}

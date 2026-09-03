<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** C5: order-placed/status-changed emails to whichever party didn't act, and a review-received email to the seller. */
class OrderAndReviewEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithProduct(?string $sellerEmail = 'seller@example.com'): array
    {
        $seller = SellerProfile::factory()->verified()->create(['user_id' => User::factory()->create(['email' => $sellerEmail])]);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 20000]);

        return [$seller, $product];
    }

    public function test_placing_an_order_emails_the_seller_when_they_have_an_email(): void
    {
        Notification::fake();
        [$seller, $product] = $this->sellerWithProduct();
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertCreated();

        Notification::assertSentTo($seller->user, OrderPlacedNotification::class);
    }

    public function test_placing_an_order_sends_no_email_when_the_seller_has_none(): void
    {
        Notification::fake();
        [$seller, $product] = $this->sellerWithProduct(null);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertCreated();

        Notification::assertNothingSentTo($seller->user);
    }

    public function test_advancing_an_order_status_emails_the_buyer_when_the_seller_advanced_it(): void
    {
        Notification::fake();
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $seller = SellerProfile::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'pending']);

        $this->actingAs($seller->user)->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertOk();

        Notification::assertSentTo($buyer, OrderStatusUpdatedNotification::class);
    }

    public function test_advancing_an_order_status_emails_the_seller_when_the_buyer_cancelled_it(): void
    {
        Notification::fake();
        $seller = SellerProfile::factory()->create(['user_id' => User::factory()->create(['email' => 'seller@example.com'])]);
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'pending']);

        $this->actingAs($buyer)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'Changed my mind'])
            ->assertOk();

        Notification::assertSentTo($seller->user, OrderStatusUpdatedNotification::class);
    }

    public function test_leaving_a_review_emails_the_seller(): void
    {
        Notification::fake();
        $seller = SellerProfile::factory()->create(['user_id' => User::factory()->create(['email' => 'seller@example.com'])]);
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'completed']);

        $this->actingAs($buyer)->postJson("/api/orders/{$order->id}/review", [
            'rating' => 5,
            'comment' => 'Great shop!',
        ])->assertCreated();

        Notification::assertSentTo($seller->user, ReviewReceivedNotification::class);
    }

    public function test_leaving_a_review_sends_no_email_when_the_seller_has_none(): void
    {
        Notification::fake();
        $seller = SellerProfile::factory()->create(['user_id' => User::factory()->create(['email' => null])]);
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'completed']);

        $this->actingAs($buyer)->postJson("/api/orders/{$order->id}/review", ['rating' => 4])
            ->assertCreated();

        Notification::assertNothingSentTo($seller->user);
    }
}

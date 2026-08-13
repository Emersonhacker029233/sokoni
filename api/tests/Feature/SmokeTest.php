<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hits every registered API endpoint at least once and asserts it doesn't
 * blow up (no 500s) — the "smoke script" called for by CLAUDE.md Phase 2's
 * verification step. Business-logic correctness is covered by the more
 * targeted tests in tests/Feature/{Auth,Orders,Reviews,Sellers,Discovery,Chat}.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_endpoint_responds_without_a_server_error(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create(['category_id' => $category->id]);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $category->id]);
        $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $review = Review::factory()->create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
        ]);
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);

        // ---- Public, unauthenticated ----
        $this->postJson('/api/auth/otp/request', ['phone' => '+255754111111'])->assertOk();
        $otpCode = Cache::get('otp:+255754111111');
        $this->postJson('/api/auth/otp/verify', ['phone' => '+255754111111', 'code' => $otpCode, 'name' => 'Smoke Test'])->assertOk();
        $this->postJson('/api/auth/social', ['provider' => 'google', 'token' => 'invalid'])->assertStatus(422);

        $this->getJson('/api/categories')->assertOk();
        $this->getJson('/api/products')->assertOk();
        $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->getJson('/api/sellers')->assertOk();
        $this->getJson("/api/sellers/{$seller->handle}")->assertOk();
        $this->getJson("/api/sellers/{$seller->handle}/reviews")->assertOk();

        // ---- Authenticated as buyer ----
        $this->actingAs($buyer);
        $this->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/terms/accept', ['version' => '1.0'])->assertOk();
        $this->postJson('/api/devices', ['fcm_token' => 'tok-abc', 'platform' => 'android'])->assertOk();

        $this->getJson('/api/favorites')->assertOk();
        $this->postJson("/api/products/{$product->id}/favorite")->assertOk();
        $this->deleteJson("/api/products/{$product->id}/favorite")->assertOk();

        $this->getJson('/api/orders')->assertOk();
        $this->getJson("/api/orders/{$order->id}")->assertOk();

        $newOrderId = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/conversations')->assertOk();
        $this->getJson("/api/conversations/{$conversation->id}")->assertOk();
        $this->getJson("/api/conversations/{$conversation->id}/messages")->assertOk();
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'hi'])->assertCreated();

        $this->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => $product->id,
            'reason' => 'Spam',
        ])->assertCreated();

        $this->postJson('/api/sellers', [
            'shop_name' => 'Smoke Shop',
            'handle' => 'smokeshop1',
            'category_id' => $category->id,
        ])->assertCreated();
        $mySeller = SellerProfile::query()->where('handle', 'smokeshop1')->firstOrFail();
        $this->patchJson("/api/sellers/{$mySeller->id}/location", [
            'lat' => -6.79, 'lng' => 39.20, 'address' => '1 Test St', 'region' => 'Dar es Salaam', 'district' => 'Ilala',
        ])->assertOk();
        $this->patchJson("/api/sellers/{$mySeller->id}", ['bio' => 'Updated bio'])->assertOk();
        $this->patchJson("/api/sellers/{$mySeller->id}/identity", [
            'nida_number' => str_repeat('1', 20),
            'nida_image' => UploadedFile::fake()->image('nida.jpg'),
        ])->assertOk();
        $this->patchJson("/api/sellers/{$mySeller->id}/licence", [
            'licence_file' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->postJson('/api/products', [
            'category_id' => $category->id, 'title' => 'Smoke product', 'price' => 1000, 'stock' => 1, 'condition' => 'new',
        ])->assertCreated();
        $myProduct = Product::query()->where('title', 'Smoke product')->firstOrFail();
        $this->patchJson("/api/products/{$myProduct->id}", ['price' => 1200])->assertOk();
        $this->deleteJson("/api/products/{$myProduct->id}")->assertOk();

        // ---- Authenticated as the seller who can act on the order/review above ----
        $this->actingAs($seller->user);
        $this->getJson('/api/shop/orders')->assertOk();
        $this->patchJson("/api/orders/{$newOrderId}/status", ['status' => 'accepted'])->assertOk();
        $this->patchJson("/api/reviews/{$review->id}/reply", ['reply' => 'Thanks!'])->assertOk();

        $this->postJson('/api/auth/logout')->assertOk();
    }
}

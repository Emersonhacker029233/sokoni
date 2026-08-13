<?php

namespace Tests\Feature\Sellers;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_sellers_products_are_hidden_from_the_public_feed(): void
    {
        $seller = SellerProfile::factory()->create(); // status: pending
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->getJson('/api/products')->assertOk()->assertJsonMissing(['id' => $product->id]);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_verified_sellers_products_appear_in_the_feed(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->getJson('/api/products')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($product->id));

        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_pending_seller_can_still_preview_their_own_hidden_product(): void
    {
        $seller = SellerProfile::factory()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller->user)
            ->getJson("/api/products/{$product->id}")
            ->assertOk();
    }

    public function test_verifying_a_seller_makes_their_products_appear_in_the_feed(): void
    {
        $seller = SellerProfile::factory()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();

        // Simulates the Filament admin verification action (built in Phase
        // 9) — the visibility rule itself lives on the model/scope, tested
        // here independently of the admin UI. forceFill because status/
        // verified_at are deliberately non-fillable (admin-only).
        $seller->forceFill(['status' => 'verified', 'verified_at' => now()])->save();

        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_pending_seller_can_still_create_products(): void
    {
        $seller = SellerProfile::factory()->create();
        $category = \App\Models\Category::factory()->create();

        $this->actingAs($seller->user)->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'My first listing',
            'price' => 15000,
            'stock' => 3,
            'condition' => 'new',
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['seller_id' => $seller->id, 'title' => 'My first listing']);
    }

    public function test_a_buyer_without_a_seller_profile_cannot_create_products(): void
    {
        $buyer = User::factory()->create();
        $category = \App\Models\Category::factory()->create();

        $this->actingAs($buyer)->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'Not allowed',
            'price' => 1000,
            'stock' => 1,
            'condition' => 'new',
        ])->assertForbidden();
    }

    public function test_handle_must_be_unique_and_not_reserved(): void
    {
        $user = User::factory()->create();
        $category = \App\Models\Category::factory()->create();
        SellerProfile::factory()->create(['handle' => 'takenhandle']);

        $this->actingAs($user)->postJson('/api/sellers', [
            'shop_name' => 'My Shop',
            'handle' => 'takenhandle',
            'category_id' => $category->id,
        ])->assertStatus(422);

        $this->actingAs($user)->postJson('/api/sellers', [
            'shop_name' => 'My Shop',
            'handle' => 'admin',
            'category_id' => $category->id,
        ])->assertStatus(422);
    }

    public function test_a_seller_cannot_have_two_profiles(): void
    {
        $user = User::factory()->create();
        SellerProfile::factory()->create(['user_id' => $user->id]);
        $category = \App\Models\Category::factory()->create();

        $this->actingAs($user)->postJson('/api/sellers', [
            'shop_name' => 'Second Shop',
            'handle' => 'secondshop',
            'category_id' => $category->id,
        ])->assertForbidden();
    }
}

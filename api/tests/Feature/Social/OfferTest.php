<?php

namespace Tests\Feature\Social;

use App\Models\Offer;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    /** Same schema-level guarantee as Update — product_id is NOT NULL. */
    public function test_the_database_rejects_an_offer_with_no_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->expectException(QueryException::class);

        Offer::query()->create([
            'seller_id' => $seller->id,
            'product_id' => null,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'price_snapshot' => 10000,
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
        ]);
    }

    public function test_a_seller_can_run_a_percentage_offer_on_their_own_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 20000]);

        $response = $this->actingAs($seller->user)->postJson('/api/offers', [
            'product_id' => $product->id,
            'discount_type' => 'percent',
            'discount_value' => 25,
            'duration_days' => 3,
        ])->assertCreated();

        $response->assertJsonPath('data.discounted_price', 15000);
        $this->assertDatabaseHas('offers', ['seller_id' => $seller->id, 'product_id' => $product->id]);
    }

    public function test_a_fixed_price_offer_must_be_less_than_the_current_price(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 20000]);

        $this->actingAs($seller->user)->postJson('/api/offers', [
            'product_id' => $product->id,
            'discount_type' => 'fixed_price',
            'discount_value' => 25000,
            'duration_days' => 3,
        ])->assertStatus(422);
    }

    public function test_a_percentage_offer_must_be_between_1_and_90(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller->user)->postJson('/api/offers', [
            'product_id' => $product->id,
            'discount_type' => 'percent',
            'discount_value' => 95,
            'duration_days' => 3,
        ])->assertStatus(422);
    }

    public function test_a_seller_cannot_run_an_offer_on_another_sellers_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller->user)->postJson('/api/offers', [
            'product_id' => $otherProduct->id,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'duration_days' => 3,
        ])->assertStatus(422);
    }

    public function test_duration_must_be_between_1_and_7_days(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller->user)->postJson('/api/offers', [
            'product_id' => $product->id,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'duration_days' => 10,
        ])->assertStatus(422);
    }

    public function test_expired_offers_do_not_appear_in_the_public_feed(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_active' => true, 'is_hidden' => false]);
        $live = Offer::factory()->create(['seller_id' => $seller->id, 'product_id' => $product->id]);
        $expired = Offer::factory()->expired()->create(['seller_id' => $seller->id, 'product_id' => $product->id]);

        $ids = collect($this->getJson('/api/offers')->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($live->id));
        $this->assertFalse($ids->contains($expired->id));
    }

    public function test_only_the_owning_seller_can_end_an_offer_early(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $offer = Offer::factory()->create(['seller_id' => $seller->id, 'product_id' => $product->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson("/api/offers/{$offer->id}")->assertForbidden();

        $this->actingAs($seller->user)->deleteJson("/api/offers/{$offer->id}")->assertOk();
        $this->assertDatabaseMissing('offers', ['id' => $offer->id]);
    }
}

<?php

namespace Tests\Feature\Social;

use App\Models\Customer;
use App\Models\Offer;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedSeller(): SellerProfile
    {
        return SellerProfile::factory()->verified()->create();
    }

    public function test_the_feed_is_browsable_without_an_account(): void
    {
        Product::factory()->count(3)->create(['seller_id' => $this->verifiedSeller()->id]);

        $this->getJson('/api/feed')->assertOk();
    }

    public function test_a_followed_shops_listing_appears_ahead_of_a_non_followed_one(): void
    {
        $buyer = User::factory()->create();
        $followedSeller = $this->verifiedSeller();
        $otherSeller = $this->verifiedSeller();

        Customer::create(['buyer_id' => $buyer->id, 'seller_id' => $followedSeller->id]);

        $followedProduct = Product::factory()->create(['seller_id' => $followedSeller->id, 'created_at' => now()->subDay()]);
        $otherProduct = Product::factory()->create(['seller_id' => $otherSeller->id, 'created_at' => now()]);

        $response = $this->actingAs($buyer)->getJson('/api/feed')->assertOk();

        $productItems = collect($response->json('data'))->where('type', 'product')->values();
        $ids = $productItems->pluck('data.id');

        $this->assertSame(
            $ids->search($followedProduct->id),
            0,
            'the followed seller\'s Listing should be the first product item in the feed'
        );
        $this->assertTrue($ids->search($followedProduct->id) < $ids->search($otherProduct->id));
    }

    public function test_hidden_and_unverified_sellers_products_never_appear_in_the_feed(): void
    {
        $pendingSeller = SellerProfile::factory()->create(); // status defaults to pending
        Product::factory()->create(['seller_id' => $pendingSeller->id]);

        $response = $this->getJson('/api/feed')->assertOk();

        $this->assertEmpty(collect($response->json('data'))->where('type', 'product'));
    }

    public function test_a_running_offer_appears_in_the_feed_as_its_own_item_type(): void
    {
        $seller = $this->verifiedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        Offer::create([
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'price_snapshot' => $product->price,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/feed')->assertOk();

        $offerItems = collect($response->json('data'))->where('type', 'offer');
        $this->assertNotEmpty($offerItems);
    }

    public function test_showcases_are_woven_into_the_feed_at_a_fixed_interval(): void
    {
        $seller = $this->verifiedSeller();
        Product::factory()->count(8)->create(['seller_id' => $seller->id]);
        $showcaseProduct = Product::factory()->create(['seller_id' => $seller->id]);
        Showcase::create([
            'seller_id' => $seller->id,
            'product_id' => $showcaseProduct->id,
            'video_path' => 'seed/showcase.mp4',
            'thumb_path' => 'seed/showcase.jpg',
            'duration' => 20,
        ]);

        $response = $this->getJson('/api/feed')->assertOk();

        $types = collect($response->json('data'))->pluck('type');
        // 6 real items then a showcase (CLAUDE.md: "inline every 6-8 items").
        $this->assertSame('showcase', $types[6]);
    }

    public function test_a_sponsored_listing_is_flagged_and_exposes_its_chosen_contact_method(): void
    {
        $seller = $this->verifiedSeller();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_sponsored' => true,
            'sponsored_until' => now()->addDay(),
            'sponsor_contact_method' => 'whatsapp',
        ]);

        $response = $this->getJson('/api/feed')->assertOk();

        $item = collect($response->json('data'))
            ->first(fn ($i) => $i['type'] === 'product' && $i['data']['id'] === $product->id);

        $this->assertTrue($item['data']['is_sponsored']);
        $this->assertSame('whatsapp', $item['data']['sponsor_contact_method']);
    }

    public function test_an_expired_sponsorship_is_not_flagged_as_sponsored(): void
    {
        $seller = $this->verifiedSeller();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_sponsored' => true,
            'sponsored_until' => now()->subHour(),
            'sponsor_contact_method' => 'call',
        ]);

        $response = $this->getJson('/api/feed')->assertOk();

        $item = collect($response->json('data'))
            ->first(fn ($i) => $i['type'] === 'product' && $i['data']['id'] === $product->id);

        $this->assertFalse($item['data']['is_sponsored']);
    }

    public function test_only_the_owning_seller_can_boost_their_own_listing(): void
    {
        $owner = User::factory()->create();
        $seller = SellerProfile::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patchJson("/api/products/{$product->id}/boost", [
                'is_sponsored' => true, 'duration_days' => 7, 'contact_method' => 'chat',
            ])
            ->assertStatus(403);

        $this->actingAs($owner)
            ->patchJson("/api/products/{$product->id}/boost", [
                'is_sponsored' => true, 'duration_days' => 7, 'contact_method' => 'chat',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_sponsored', true)
            ->assertJsonPath('data.sponsor_contact_method', 'chat');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_sponsored' => true]);
    }

    public function test_un_boosting_clears_sponsorship_fields(): void
    {
        $owner = User::factory()->create();
        $seller = SellerProfile::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_sponsored' => true,
            'sponsored_until' => now()->addDay(),
            'sponsor_contact_method' => 'call',
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/products/{$product->id}/boost", ['is_sponsored' => false])
            ->assertOk()
            ->assertJsonPath('data.is_sponsored', false);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_sponsored' => false, 'sponsored_until' => null]);
    }
}

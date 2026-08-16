<?php

namespace Tests\Feature\Social;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_follow_a_shop(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();

        $this->actingAs($buyer)->postJson("/api/sellers/{$seller->handle}/follow")->assertOk();

        $this->assertDatabaseHas('customers', ['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
    }

    public function test_following_a_shop_updates_its_denormalised_customer_count(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $buyerA = User::factory()->create();
        $buyerB = User::factory()->create();

        $this->actingAs($buyerA)->postJson("/api/sellers/{$seller->handle}/follow")->assertOk();
        $this->actingAs($buyerB)->postJson("/api/sellers/{$seller->handle}/follow")->assertOk();

        $this->assertEquals(2, $seller->fresh()->customer_count);

        $this->actingAs($buyerA)->deleteJson("/api/sellers/{$seller->handle}/follow")->assertOk();

        $this->assertEquals(1, $seller->fresh()->customer_count);
    }

    public function test_following_the_same_shop_twice_is_idempotent(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();

        $this->actingAs($buyer)->postJson("/api/sellers/{$seller->handle}/follow")->assertOk();
        $this->actingAs($buyer)->postJson("/api/sellers/{$seller->handle}/follow")->assertOk();

        $this->assertDatabaseCount('customers', 1);
        $this->assertEquals(1, $seller->fresh()->customer_count);
    }

    public function test_a_seller_cannot_follow_their_own_shop(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->actingAs($seller->user)->postJson("/api/sellers/{$seller->handle}/follow")->assertStatus(422);
    }
}

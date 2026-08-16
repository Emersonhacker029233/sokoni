<?php

namespace Tests\Feature\Sellers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_see_real_dashboard_stats(): void
    {
        $owner = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'views' => 42]);

        $buyer = User::factory()->create();
        $buyer->favorites()->attach($product->id);

        Order::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'created_at' => now()]);
        Order::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'created_at' => now()->subDays(45)]);

        $response = $this->actingAs($owner)
            ->getJson("/api/sellers/{$seller->id}/dashboard")
            ->assertOk();

        $response->assertJsonPath('data.total_views', 42);
        $response->assertJsonPath('data.saves_last_30_days', 1);
        $response->assertJsonPath('data.orders_last_30_days', 1);
    }

    public function test_a_stranger_cannot_see_another_sellers_dashboard(): void
    {
        $seller = SellerProfile::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/sellers/{$seller->id}/dashboard")
            ->assertStatus(403);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $seller = SellerProfile::factory()->create();

        $this->getJson("/api/sellers/{$seller->id}/dashboard")->assertStatus(401);
    }
}

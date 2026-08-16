<?php

namespace Tests\Feature\Sellers;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_upload_a_shop_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $seller = SellerProfile::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->patchJson("/api/sellers/{$seller->id}/logo", ['logo' => UploadedFile::fake()->image('logo.jpg')])
            ->assertOk();

        $this->assertNotNull($response->json('data.logo'));
        $this->assertNotNull($seller->refresh()->logo);
    }

    public function test_a_stranger_cannot_upload_another_sellers_logo(): void
    {
        Storage::fake('public');
        $seller = SellerProfile::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patchJson("/api/sellers/{$seller->id}/logo", ['logo' => UploadedFile::fake()->image('logo.jpg')])
            ->assertStatus(403);
    }

    public function test_the_logo_appears_on_the_compact_seller_embed(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $owner->id]);
        $product = \App\Models\Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($owner)->patchJson("/api/sellers/{$seller->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ])->assertOk();

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->assertNotNull($response->json('data.seller.logo'));
    }
}

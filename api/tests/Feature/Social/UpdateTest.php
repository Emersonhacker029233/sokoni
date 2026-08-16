<?php

namespace Tests\Feature\Social;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Update;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * The actual hard rule (CLAUDE.md Part 3: "a schema guarantee, not a
     * policy") — seller_id is NOT NULL at the database level, so there is
     * no way for an Update row to exist without a seller behind it,
     * independent of any application-level check.
     */
    public function test_the_database_rejects_an_update_with_no_seller(): void
    {
        $this->expectException(QueryException::class);

        Update::query()->create([
            'seller_id' => null,
            'type' => 'image',
            'media_path' => 'x',
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_a_seller_can_post_an_update(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $response = $this->actingAs($seller->user)->post('/api/updates', [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('notice.jpg', 1200, 1200),
            'caption' => 'Fresh stock just arrived!',
        ])->assertCreated();

        $response->assertJsonPath('data.caption', 'Fresh stock just arrived!');
        $this->assertDatabaseHas('updates', ['seller_id' => $seller->id]);
    }

    public function test_an_update_can_reference_one_of_the_sellers_own_products(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller->user)->post('/api/updates', [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('notice.jpg'),
            'product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.product.id', $product->id);
    }

    public function test_an_update_cannot_reference_another_sellers_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller->user)->post('/api/updates', [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('notice.jpg'),
            'product_id' => $otherProduct->id,
        ])->assertStatus(422);
    }

    public function test_a_buyer_without_a_shop_cannot_post_an_update(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post('/api/updates', [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('notice.jpg'),
        ])->assertForbidden();
    }

    public function test_expired_updates_do_not_appear_in_the_public_feed(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $live = Update::factory()->for($seller, 'seller')->create();
        $expired = Update::factory()->for($seller, 'seller')->expired()->create();

        $ids = collect($this->getJson('/api/updates')->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($live->id));
        $this->assertFalse($ids->contains($expired->id));
    }

    public function test_updates_from_unverified_sellers_are_not_publicly_visible(): void
    {
        $pendingSeller = SellerProfile::factory()->create(); // defaults to 'pending'
        $update = Update::factory()->for($pendingSeller, 'seller')->create();

        $ids = collect($this->getJson('/api/updates')->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($update->id));
    }

    public function test_only_the_owning_seller_can_delete_their_update(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $update = Update::factory()->for($seller, 'seller')->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson("/api/updates/{$update->id}")->assertForbidden();

        $this->actingAs($seller->user)->deleteJson("/api/updates/{$update->id}")->assertOk();
        $this->assertDatabaseMissing('updates', ['id' => $update->id]);
    }

    public function test_the_scheduled_command_deletes_expired_updates(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        Update::factory()->for($seller, 'seller')->expired()->count(3)->create();
        Update::factory()->for($seller, 'seller')->create();

        $this->artisan('updates:delete-expired')->assertSuccessful();

        $this->assertDatabaseCount('updates', 1);
    }
}

<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_seller_can_upload_an_image_and_gets_three_sizes_back(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAs($seller->user)->post("/api/products/{$product->id}/media", [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('photo.jpg', 1600, 1600),
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'image');
        $this->assertNotNull($response->json('data.path'));
        $this->assertNotNull($response->json('data.card_path'));
        $this->assertNotNull($response->json('data.thumb_path'));
        $this->assertDatabaseHas('product_media', ['product_id' => $product->id, 'type' => 'image']);
    }

    public function test_video_upload_requires_a_client_generated_thumbnail(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller->user)->post("/api/products/{$product->id}/media", [
            'type' => 'video',
            'file' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
            'duration' => 12,
        ])->assertStatus(422);

        $response = $this->actingAs($seller->user)->post("/api/products/{$product->id}/media", [
            'type' => 'video',
            'file' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 800, 800),
            'duration' => 12,
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'video');
        $response->assertJsonPath('data.duration', 12);
    }

    public function test_a_product_cannot_have_more_than_eight_media_items(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $product->media()->createMany(
            array_fill(0, 8, ['type' => 'image', 'path' => 'x', 'thumb_path' => 'x', 'sort' => 0])
        );

        $this->actingAs($seller->user)->post("/api/products/{$product->id}/media", [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(422);
    }

    public function test_only_the_owning_seller_can_upload_or_delete_media(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post("/api/products/{$product->id}/media", [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();

        $media = $product->media()->create(['type' => 'image', 'path' => 'x', 'thumb_path' => 'x', 'sort' => 0]);

        $this->actingAs($stranger)
            ->deleteJson("/api/products/{$product->id}/media/{$media->id}")
            ->assertForbidden();

        $this->actingAs($seller->user)
            ->deleteJson("/api/products/{$product->id}/media/{$media->id}")
            ->assertOk();

        $this->assertDatabaseMissing('product_media', ['id' => $media->id]);
    }

    public function test_shop_products_endpoint_includes_hidden_products(): void
    {
        $seller = SellerProfile::factory()->create(); // defaults to 'pending'
        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_hidden' => true]);

        $response = $this->actingAs($seller->user)->getJson('/api/shop/products')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($product->id));
    }

    /**
     * Part 3 (client feedback): "reordering so the seller chooses the
     * cover image" — mirrors Web\Account\ShopProductMediaControllerTest's
     * own reorder test exactly, against the API's Sanctum-authenticated
     * equivalent instead.
     */
    public function test_seller_can_reorder_their_products_media_which_sets_the_new_cover(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $first = $product->media()->create(['type' => 'image', 'path' => 'a', 'thumb_path' => 'a', 'sort' => 0]);
        $second = $product->media()->create(['type' => 'image', 'path' => 'b', 'thumb_path' => 'b', 'sort' => 1]);

        $this->actingAs($seller->user)
            ->postJson("/api/products/{$product->id}/media/reorder", ['order' => [$second->id, $first->id]])
            ->assertOk();

        $this->assertSame(0, $second->fresh()->sort);
        $this->assertSame(1, $first->fresh()->sort);
    }

    public function test_reorder_rejects_a_list_that_does_not_exactly_match_the_products_own_media(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $ownMedia = $product->media()->create(['type' => 'image', 'path' => 'a', 'thumb_path' => 'a', 'sort' => 0]);
        $foreignMedia = Product::factory()->create()->media()->create([
            'type' => 'image', 'path' => 'x', 'thumb_path' => 'x', 'sort' => 0,
        ]);

        $this->actingAs($seller->user)
            ->postJson("/api/products/{$product->id}/media/reorder", ['order' => [$foreignMedia->id]])
            ->assertStatus(422);

        $this->actingAs($seller->user)
            ->postJson("/api/products/{$product->id}/media/reorder", ['order' => [$ownMedia->id, $foreignMedia->id]])
            ->assertStatus(422);
    }

    public function test_a_stranger_cannot_reorder_someone_elses_product_media(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $media = $product->media()->create(['type' => 'image', 'path' => 'a', 'thumb_path' => 'a', 'sort' => 0]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/products/{$product->id}/media/reorder", ['order' => [$media->id]])
            ->assertForbidden();
    }
}

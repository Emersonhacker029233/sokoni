<?php

namespace Tests\Feature\Social;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** Same schema-level guarantee as Update/Offer — product_id is NOT NULL. */
    public function test_the_database_rejects_a_showcase_with_no_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->expectException(QueryException::class);

        Showcase::query()->create([
            'seller_id' => $seller->id,
            'product_id' => null,
            'video_path' => 'x',
            'thumb_path' => 'x',
            'duration' => 10,
        ]);
    }

    public function test_a_seller_can_upload_a_showcase_for_their_own_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAs($seller->user)->post('/api/showcases', [
            'product_id' => $product->id,
            'file' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 800, 800),
            'duration' => 25,
        ])->assertCreated();

        $response->assertJsonPath('data.product.id', $product->id);
        $this->assertDatabaseHas('showcases', ['seller_id' => $seller->id, 'product_id' => $product->id]);
    }

    public function test_a_seller_cannot_showcase_another_sellers_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller->user)->post('/api/showcases', [
            'product_id' => $otherProduct->id,
            'file' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('poster.jpg'),
            'duration' => 25,
        ])->assertStatus(422);
    }

    public function test_viewing_a_showcase_increments_its_view_count(): void
    {
        $showcase = Showcase::factory()->create(['views' => 0]);

        $this->getJson("/api/showcases/{$showcase->id}")->assertOk();

        $this->assertEquals(1, $showcase->fresh()->views);
    }

    public function test_showcases_for_hidden_products_are_not_publicly_visible(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->hidden()->create(['seller_id' => $seller->id]);
        $showcase = Showcase::factory()->create(['seller_id' => $seller->id, 'product_id' => $product->id]);

        $ids = collect($this->getJson('/api/showcases')->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($showcase->id));
    }

    public function test_showcases_from_unverified_sellers_are_not_publicly_visible(): void
    {
        $pendingSeller = SellerProfile::factory()->create(); // defaults to 'pending'
        $product = Product::factory()->create(['seller_id' => $pendingSeller->id]);
        $showcase = Showcase::factory()->create(['seller_id' => $pendingSeller->id, 'product_id' => $product->id]);

        $ids = collect($this->getJson('/api/showcases')->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($showcase->id));
    }

    public function test_only_the_owning_seller_can_delete_a_showcase(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $showcase = Showcase::factory()->create(['seller_id' => $seller->id, 'product_id' => $product->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson("/api/showcases/{$showcase->id}")->assertForbidden();

        $this->actingAs($seller->user)->deleteJson("/api/showcases/{$showcase->id}")->assertOk();
        $this->assertDatabaseMissing('showcases', ['id' => $showcase->id]);
    }
}

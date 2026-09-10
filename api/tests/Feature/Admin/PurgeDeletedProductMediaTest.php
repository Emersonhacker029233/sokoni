<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\PurgeDeletedProductMedia;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * C2 (tester feedback): a soft-deleted product stays recoverable
 * indefinitely, but its media files/rows are permanently purged once the
 * 30-day retention window passes — this is what proves that actually
 * happens, and only to media past the window.
 */
class PurgeDeletedProductMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function mediaFor(Product $product): ProductMedia
    {
        $path = 'products/'.$product->id.'/photo.jpg';
        Storage::disk('public')->put($path, 'fake-image-bytes');

        return ProductMedia::factory()->create([
            'product_id' => $product->id,
            'path' => Storage::disk('public')->url($path),
            'thumb_path' => Storage::disk('public')->url($path),
            'card_path' => Storage::disk('public')->url($path),
        ]);
    }

    public function test_it_purges_media_for_products_deleted_past_the_retention_window(): void
    {
        $product = Product::factory()->create();
        $media = $this->mediaFor($product);
        $product->delete();
        $product->forceFill(['deleted_at' => now()->subDays(PurgeDeletedProductMedia::RETENTION_DAYS + 1)])->save();

        $relativePath = str($media->path)->after(Storage::disk('public')->url(''))->toString();
        Storage::disk('public')->assertExists($relativePath);

        $this->artisan('products:purge-deleted-media')->assertSuccessful();

        Storage::disk('public')->assertMissing($relativePath);
        $this->assertDatabaseMissing('product_media', ['id' => $media->id]);
        // The product row itself survives, still soft-deleted — recoverable, just without its old photos.
        $this->assertSoftDeleted($product);
    }

    public function test_it_leaves_media_alone_for_products_still_within_the_retention_window(): void
    {
        $product = Product::factory()->create();
        $media = $this->mediaFor($product);
        $product->delete();
        $product->forceFill(['deleted_at' => now()->subDays(5)])->save();

        $this->artisan('products:purge-deleted-media')->assertSuccessful();

        $this->assertDatabaseHas('product_media', ['id' => $media->id]);
    }

    public function test_it_never_touches_media_for_products_that_are_not_deleted_at_all(): void
    {
        $product = Product::factory()->create();
        $media = $this->mediaFor($product);

        $this->artisan('products:purge-deleted-media')->assertSuccessful();

        $this->assertDatabaseHas('product_media', ['id' => $media->id]);
    }
}

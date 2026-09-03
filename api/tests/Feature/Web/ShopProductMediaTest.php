<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The web product form's AJAX photo manager (tester feedback item 1) —
 * these endpoints are the web-guard equivalent of Api\ProductMediaController,
 * so coverage mirrors ProductMediaTest's shape: ownership, the shared
 * Settings::maxMediaPerProduct() cap, and (new to the web surface) reorder.
 */
class ShopProductMediaTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedSeller(): array
    {
        $user = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'sell',
        ]);
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $user->id]);

        return [$user, $seller];
    }

    public function test_a_seller_can_upload_a_photo_to_their_own_product(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAsWebUser($user)->postJson(
            route('web.account.shop.products.media.store', $product),
            ['type' => 'image', 'file' => UploadedFile::fake()->image('photo.jpg')]
        );

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'thumb_path', 'sort']);
        $this->assertCount(1, $product->media()->get());
    }

    public function test_a_seller_cannot_upload_to_someone_elses_product(): void
    {
        Storage::fake('public');
        [$user] = $this->onboardedSeller();
        $otherProduct = Product::factory()->create();

        $this->actingAsWebUser($user)->postJson(
            route('web.account.shop.products.media.store', $otherProduct),
            ['type' => 'image', 'file' => UploadedFile::fake()->image('photo.jpg')]
        )->assertForbidden();
    }

    public function test_upload_is_rejected_once_the_product_hits_the_media_cap(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        ProductMedia::factory()->count(8)->create(['product_id' => $product->id]);

        $this->actingAsWebUser($user)->postJson(
            route('web.account.shop.products.media.store', $product),
            ['type' => 'image', 'file' => UploadedFile::fake()->image('photo.jpg')]
        )->assertUnprocessable();

        $this->assertCount(8, $product->media()->get());
    }

    public function test_a_non_image_type_or_missing_file_is_rejected(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAsWebUser($user)->postJson(
            route('web.account.shop.products.media.store', $product),
            ['type' => 'image']
        )->assertUnprocessable();
    }

    public function test_a_seller_can_delete_their_own_products_photo(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $media = ProductMedia::factory()->create(['product_id' => $product->id]);

        $this->actingAsWebUser($user)
            ->deleteJson(route('web.account.shop.products.media.destroy', [$product, $media]))
            ->assertOk();

        $this->assertModelMissing($media);
    }

    public function test_a_seller_cannot_delete_another_sellers_photo(): void
    {
        [$user] = $this->onboardedSeller();
        $otherProduct = Product::factory()->create();
        $media = ProductMedia::factory()->create(['product_id' => $otherProduct->id]);

        $this->actingAsWebUser($user)
            ->deleteJson(route('web.account.shop.products.media.destroy', [$otherProduct, $media]))
            ->assertForbidden();

        $this->assertModelExists($media);
    }

    public function test_a_seller_can_reorder_their_products_photos_which_sets_the_new_cover(): void
    {
        [$user, $seller] = $this->onboardedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $first = ProductMedia::factory()->create(['product_id' => $product->id, 'sort' => 0]);
        $second = ProductMedia::factory()->create(['product_id' => $product->id, 'sort' => 1]);

        $this->actingAsWebUser($user)
            ->postJson(route('web.account.shop.products.media.reorder', $product), [
                'order' => [$second->id, $first->id],
            ])
            ->assertOk();

        $this->assertSame(0, $second->fresh()->sort);
        $this->assertSame(1, $first->fresh()->sort);
    }

    public function test_reorder_rejects_a_list_that_does_not_exactly_match_the_products_own_media(): void
    {
        [$user, $seller] = $this->onboardedSeller();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $ownMedia = ProductMedia::factory()->create(['product_id' => $product->id]);
        $foreignMedia = ProductMedia::factory()->create();

        $this->actingAsWebUser($user)
            ->postJson(route('web.account.shop.products.media.reorder', $product), [
                'order' => [$foreignMedia->id],
            ])
            ->assertStatus(422);

        $this->actingAsWebUser($user)
            ->postJson(route('web.account.shop.products.media.reorder', $product), [
                'order' => [$ownMedia->id, $foreignMedia->id],
            ])
            ->assertUnprocessable();
    }

    public function test_reorder_requires_ownership_of_the_product(): void
    {
        [$user] = $this->onboardedSeller();
        $otherProduct = Product::factory()->create();
        $media = ProductMedia::factory()->create(['product_id' => $otherProduct->id]);

        $this->actingAsWebUser($user)
            ->postJson(route('web.account.shop.products.media.reorder', $otherProduct), [
                'order' => [$media->id],
            ])
            ->assertForbidden();
    }
}

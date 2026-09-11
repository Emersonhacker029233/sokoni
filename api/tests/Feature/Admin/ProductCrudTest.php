<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductMedia\Pages\ListProductMedia;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * D1 (tester feedback): "full CRUD for products... create, edit every
 * field... media with individual image removal." ProductResource had no
 * create/edit pages at all before this — only a view page — since an
 * earlier round deliberately left products as a seller-only, admin-can-
 * only-moderate surface. This proves the new admin-side create/edit/media
 * actually persists real data, not just that the pages render.
 */
class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function electronicsAndTvs(): array
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();

        return [$electronics, $tvs];
    }

    public function test_an_admin_can_create_a_product_with_a_subcategory(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->verified()->create();
        [$electronics, $tvs] = $this->electronicsAndTvs();

        $this->actingAsAdmin($admin);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'title' => 'Admin-created TV',
                'description' => 'A fine television.',
                'category_parent_id' => $electronics->id,
                'category_id' => $tvs->id,
                'condition' => 'new',
                'price' => 450000,
                'stock' => 3,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('title', 'Admin-created TV')->firstOrFail();
        $this->assertSame($seller->id, $product->seller_id);
        $this->assertSame($tvs->id, $product->category_id);
        $this->assertSame(450000, $product->price);
        $this->assertSame(3, $product->stock);
        $this->assertTrue($product->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.created', 'subject_id' => $product->id]);
    }

    /** Picking a top-level category with no subcategory chosen must fall back to the parent itself, not be left unset. */
    public function test_an_admin_can_create_a_product_directly_under_a_parent_category(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->verified()->create();
        [$electronics] = $this->electronicsAndTvs();

        $this->actingAsAdmin($admin);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'title' => 'Generic electronics item',
                'category_parent_id' => $electronics->id,
                'category_id' => $electronics->id,
                'condition' => 'used',
                'price' => 10000,
                'stock' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('title', 'Generic electronics item')->firstOrFail();
        $this->assertSame($electronics->id, $product->category_id);
    }

    public function test_an_admin_can_edit_every_field_of_an_existing_product(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->verified()->create();
        $otherSeller = SellerProfile::factory()->verified()->create();
        [$electronics, $tvs] = $this->electronicsAndTvs();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $tvs->id,
            'title' => 'Old title',
            'price' => 1000,
            'stock' => 1,
            'condition' => 'used',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'seller_id' => $otherSeller->id,
                'title' => 'New title',
                'description' => 'Updated description',
                'category_parent_id' => $electronics->id,
                'category_id' => $electronics->id,
                'condition' => 'new',
                'price' => 999000,
                'stock' => 7,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame($otherSeller->id, $product->seller_id);
        $this->assertSame('New title', $product->title);
        $this->assertSame('Updated description', $product->description);
        $this->assertSame($electronics->id, $product->category_id);
        $this->assertSame('new', $product->condition);
        $this->assertSame(999000, $product->price);
        $this->assertSame(7, $product->stock);
        $this->assertFalse($product->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.updated', 'subject_id' => $product->id]);
    }

    public function test_editing_a_product_pre_selects_its_current_category_and_subcategory(): void
    {
        $admin = User::factory()->admin()->create();
        [$electronics, $tvs] = $this->electronicsAndTvs();
        $product = Product::factory()->create(['category_id' => $tvs->id]);

        $this->actingAsAdmin($admin);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet([
                'category_parent_id' => $electronics->id,
                'category_id' => $tvs->id,
            ]);
    }

    /**
     * The RelationManager's "Add photo" action is a thin Livewire/Filament
     * wrapper (mount a FileUpload, hand its stored temp file to
     * ProductMedia::createFromUpload()) — Livewire's own test harness has
     * a known limitation synthesizing a file upload that lives inside a
     * mounted Action's form data (works fine for a page-level form, see
     * BannerResourceTest, but not for an Action's), unrelated to anything
     * this app does. So this proves the actual persistence logic directly
     * — the one line the action's `using()` closure itself contains once
     * the tmp file is resolved — rather than driving it through that
     * unrelated Livewire testing gap.
     */
    public function test_an_admin_can_add_a_photo_to_a_product(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $media = ProductMedia::createFromUpload($product, UploadedFile::fake()->image('photo.jpg', 1600, 1600));

        $this->assertSame('image', $media->type);
        $this->assertSame($product->id, $media->product_id);
        Storage::disk('public')->assertExists(str($media->thumb_path)->after(Storage::disk('public')->url('')));
        Storage::disk('public')->assertExists(str($media->card_path)->after(Storage::disk('public')->url('')));
        Storage::disk('public')->assertExists(str($media->path)->after(Storage::disk('public')->url('')));
    }

    /**
     * The RelationManager's header action is reachable/visible at all —
     * the actual upload mechanics are proven above. Relation managers
     * lazy-load their content behind a Livewire partial by default (a
     * plain page GET never contains "Add photo" in its initial HTML,
     * regardless of whether the manager is correctly wired up), so this
     * mounts the manager component directly rather than asserting against
     * the outer EditProduct page's raw response.
     */
    public function test_the_media_relation_manager_is_visible_on_a_products_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(MediaRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertTableActionExists('create');
    }

    /**
     * D1: "media with individual image removal." Also the regression
     * proof for ProductMedia::deleteWithFiles() — a plain $record->delete()
     * would pass this test's row-count assertion but leak the physical
     * files, which the Storage::assertMissing calls below catch.
     */
    public function test_an_admin_can_remove_a_single_photo_and_its_files_are_deleted(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        Storage::disk('public')->put('products/1/full_a.jpg', 'x');
        Storage::disk('public')->put('products/1/card_a.jpg', 'x');
        Storage::disk('public')->put('products/1/thumb_a.jpg', 'x');
        $media = $product->media()->create([
            'type' => 'image',
            'path' => Storage::disk('public')->url('products/1/full_a.jpg'),
            'card_path' => Storage::disk('public')->url('products/1/card_a.jpg'),
            'thumb_path' => Storage::disk('public')->url('products/1/thumb_a.jpg'),
            'sort' => 0,
        ]);
        $keptMedia = ProductMedia::factory()->create(['product_id' => $product->id]);

        $this->actingAsAdmin($admin);

        Livewire::test(MediaRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('delete', $media);

        $this->assertDatabaseMissing('product_media', ['id' => $media->id]);
        $this->assertDatabaseHas('product_media', ['id' => $keptMedia->id]);
        Storage::disk('public')->assertMissing('products/1/full_a.jpg');
        Storage::disk('public')->assertMissing('products/1/card_a.jpg');
        Storage::disk('public')->assertMissing('products/1/thumb_a.jpg');
        $this->assertDatabaseHas('activity_logs', ['action' => 'media.deleted', 'subject_id' => $product->id]);
    }

    /**
     * D1: the pre-existing, cross-product ProductMediaResource had the
     * same "delete row, leak files" bug (see ProductMedia::deleteWithFiles)
     * — this is its own dedicated regression proof, separate from the
     * per-product relation manager above.
     */
    public function test_deleting_media_from_the_global_media_resource_also_removes_its_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        Storage::disk('public')->put('products/2/full_b.jpg', 'x');
        $media = $product->media()->create([
            'type' => 'image',
            'path' => Storage::disk('public')->url('products/2/full_b.jpg'),
            'sort' => 0,
        ]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListProductMedia::class)
            ->callTableAction('delete', $media);

        $this->assertDatabaseMissing('product_media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing('products/2/full_b.jpg');
    }
}

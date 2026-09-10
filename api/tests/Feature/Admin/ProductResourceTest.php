<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_the_products_list(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(3)->create();

        $this->actingAsAdmin($admin)->get('/admin/products')->assertOk();
    }

    public function test_an_admin_can_view_a_product_with_order_history_and_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);
        Report::factory()->create(['reportable_type' => Product::class, 'reportable_id' => $product->id]);

        $this->actingAsAdmin($admin)->get("/admin/products/{$product->id}")->assertOk();
    }

    public function test_an_admin_can_hide_and_unhide_a_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['is_hidden' => false]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableAction('hide', $product)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($product->fresh()->is_hidden);

        Livewire::test(ListProducts::class)
            ->callTableAction('unhide', $product)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($product->fresh()->is_hidden);
    }

    public function test_an_admin_can_feature_a_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['is_sponsored' => false]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableAction('feature', $product)
            ->assertHasNoTableActionErrors();

        $product->refresh();
        $this->assertTrue($product->is_sponsored);
        $this->assertTrue($product->sponsored_until->isFuture());
    }

    public function test_an_admin_can_bulk_hide_products(): void
    {
        $admin = User::factory()->admin()->create();
        $products = Product::factory()->count(3)->create(['is_hidden' => false]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_hide', $products);

        $this->assertSame(3, Product::where('is_hidden', true)->count());
    }

    public function test_an_admin_can_bulk_reassign_category(): void
    {
        $admin = User::factory()->admin()->create();
        $products = Product::factory()->count(2)->create();
        $newCategory = Category::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_reassign_category', $products, data: ['category_id' => $newCategory->id]);

        $this->assertSame(2, Product::where('category_id', $newCategory->id)->count());
    }

    /**
     * C2 (tester feedback): "admin can delete products, not just hide" —
     * soft delete (recoverable), with a required reason and an activity
     * log entry, distinct from the pre-existing `hide` action.
     */
    public function test_an_admin_can_soft_delete_a_product_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product, data: ['reason' => 'Counterfeit goods'])
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'product.deleted',
            'subject_id' => $product->id,
            'subject_type' => (new Product)->getMorphClass(),
            'reason' => 'Counterfeit goods',
        ]);
    }

    public function test_deleting_a_product_without_a_reason_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertNotSoftDeleted($product);
    }

    public function test_a_soft_deleted_product_is_gone_from_the_public_feed(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $product->delete();

        $this->assertSame(0, Product::visible()->whereKey($product->id)->count());
    }

    public function test_an_admin_can_restore_a_deleted_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableAction('restore', $product)
            ->assertHasNoTableActionErrors();

        $this->assertNotSoftDeleted($product->fresh());
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.restored', 'subject_id' => $product->id]);
    }

    public function test_an_admin_can_bulk_delete_products_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $products = Product::factory()->count(3)->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_delete', $products, data: ['reason' => 'Bulk cleanup of expired listings']);

        foreach ($products as $product) {
            $this->assertSoftDeleted($product);
        }
        $this->assertSame(3, ActivityLog::where('action', 'product.deleted')->where('reason', 'Bulk cleanup of expired listings')->count());
    }

    public function test_products_can_be_filtered_by_seller_and_searched_by_shop_name(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create(['shop_name' => 'Unique Shop Name']);
        $matching = Product::factory()->create(['seller_id' => $seller->id]);
        $other = Product::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListProducts::class)
            ->searchTable('Unique Shop Name')
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }
}

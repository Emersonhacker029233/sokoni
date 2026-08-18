<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
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

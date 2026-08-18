<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SellerProfiles\SellerProfileResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `Resource::getGlobalSearchResults()` scopes to the "current panel"
     * (via `canAccess()`/`canGloballySearch()`), normally set by
     * middleware while an actual `/admin/...` request is being routed —
     * set it explicitly here the same way a real admin request would
     * arrive at it.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_products_are_globally_searchable_by_title_and_shop_name(): void
    {
        $this->assertSame(['title', 'seller.shop_name'], ProductResource::getGloballySearchableAttributes());
    }

    public function test_shops_users_and_orders_are_globally_searchable(): void
    {
        $this->assertSame(['shop_name', 'handle'], SellerProfileResource::getGloballySearchableAttributes());
        $this->assertSame(['name', 'phone', 'email'], UserResource::getGloballySearchableAttributes());
        $this->assertSame(['code', 'buyer.name', 'seller.shop_name'], OrderResource::getGloballySearchableAttributes());
    }

    public function test_an_admin_can_find_a_product_via_global_search(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['title' => 'Unmistakable Widget Name']);
        $this->actingAsAdmin($admin);

        $results = ProductResource::getGlobalSearchResults('Unmistakable Widget');

        $this->assertSame(1, $results->count());
        $this->assertSame($product->title, $results->first()->title);
    }

    public function test_global_search_finds_a_product_by_its_shops_name_too(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create(['shop_name' => 'A Very Distinct Shop']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Generic Item']);
        $this->actingAsAdmin($admin);

        $results = ProductResource::getGlobalSearchResults('A Very Distinct Shop');

        $this->assertSame(1, $results->count());
        $this->assertSame($product->title, $results->first()->title);
    }

    public function test_an_admin_can_find_a_shop_via_global_search(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create(['shop_name' => 'One Of A Kind Shop']);
        $this->actingAsAdmin($admin);

        $results = SellerProfileResource::getGlobalSearchResults('One Of A Kind');

        $this->assertSame(1, $results->count());
        $this->assertSame($seller->shop_name, $results->first()->title);
    }

    public function test_an_admin_can_find_a_user_via_global_search(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Searchable Target Person']);
        $this->actingAsAdmin($admin);

        $results = UserResource::getGlobalSearchResults('Searchable Target');

        $this->assertSame(1, $results->count());
        $this->assertSame($target->name, $results->first()->title);
    }

    public function test_an_admin_can_find_an_order_by_code_via_global_search(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        $this->actingAsAdmin($admin);

        $results = OrderResource::getGlobalSearchResults($order->code);

        $this->assertSame(1, $results->count());
        $this->assertSame($order->code, $results->first()->title);
    }

    public function test_a_staff_user_can_still_use_global_search(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create(['title' => 'Staff Findable Item']);
        $this->actingAsAdmin($staff);

        $results = ProductResource::getGlobalSearchResults('Staff Findable');

        $this->assertSame(1, $results->count());
    }
}

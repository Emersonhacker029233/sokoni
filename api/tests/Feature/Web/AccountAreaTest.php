<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAreaTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(array $attrs = []): User
    {
        return User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
            ...$attrs,
        ]);
    }

    public function test_account_dashboard_renders(): void
    {
        $this->actingAsWebUser($this->onboardedUser())->get(route('web.account.dashboard'))->assertOk();
    }

    public function test_orders_list_and_show_render(): void
    {
        $user = $this->onboardedUser();
        $order = Order::factory()->create(['buyer_id' => $user->id]);

        $this->actingAsWebUser($user);
        $this->get(route('web.account.orders'))->assertOk();
        $this->get(route('web.account.orders.show', $order))->assertOk();
    }

    public function test_a_buyer_cannot_view_someone_elses_order(): void
    {
        $user = $this->onboardedUser();
        $otherOrder = Order::factory()->create();

        $this->actingAsWebUser($user)->get(route('web.account.orders.show', $otherOrder))->assertNotFound();
    }

    public function test_saved_items_page_renders(): void
    {
        $this->actingAsWebUser($this->onboardedUser())->get(route('web.account.saved'))->assertOk();
    }

    public function test_messages_index_renders(): void
    {
        $this->actingAsWebUser($this->onboardedUser())->get(route('web.account.messages'))->assertOk();
    }

    public function test_settings_page_renders_and_updates(): void
    {
        $user = $this->onboardedUser();
        $this->actingAsWebUser($user);

        $this->get(route('web.account.settings'))->assertOk();

        $this->post(route('web.account.settings.update'), ['name' => 'New Name', 'locale' => 'sw'])
            ->assertRedirect();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_a_buyer_without_a_shop_visiting_shop_dashboard_is_redirected_to_the_registration_form(): void
    {
        $this->actingAsWebUser($this->onboardedUser())->get(route('web.account.shop'))->assertRedirect(route('web.account.shop.register'));
    }

    public function test_a_seller_can_view_their_shop_dashboard(): void
    {
        $user = $this->onboardedUser();
        SellerProfile::factory()->verified()->create(['user_id' => $user->id]);

        $this->actingAsWebUser($user)->get(route('web.account.shop'))->assertOk();
    }

    public function test_a_seller_can_create_a_product_from_the_web_and_lands_on_the_edit_page_to_add_photos(): void
    {
        $user = $this->onboardedUser();
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        $this->actingAsWebUser($user);

        $this->get(route('web.account.shop.products.create'))->assertOk();

        $response = $this->post(route('web.account.shop.products.store'), [
            'category_id' => $category->id,
            'title' => 'A brand new product',
            'price' => 15000,
            'stock' => 3,
            'condition' => 'new',
        ]);

        $this->assertDatabaseHas('products', ['seller_id' => $seller->id, 'title' => 'A brand new product']);
        $product = Product::where('title', 'A brand new product')->first();

        // Photos are a separate step now (tester feedback item 1) — a
        // product must exist before its AJAX media manager has anywhere
        // to upload to, exactly like the Flutter app's own product form.
        $response->assertRedirect(route('web.account.shop.products.edit', $product));
        $this->assertCount(0, $product->media);
    }

    public function test_a_seller_can_edit_their_own_product_but_not_someone_elses(): void
    {
        $user = $this->onboardedUser();
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $user->id]);
        $ownProduct = Product::factory()->create(['seller_id' => $seller->id]);
        $otherProduct = Product::factory()->create();

        $this->actingAsWebUser($user);

        $this->get(route('web.account.shop.products.edit', $ownProduct))->assertOk();
        $this->get(route('web.account.shop.products.edit', $otherProduct))->assertForbidden();

        $this->put(route('web.account.shop.products.update', $ownProduct), [
            'title' => 'Updated title',
        ])->assertRedirect(route('web.account.shop.products'));

        $this->assertSame('Updated title', $ownProduct->fresh()->title);
    }
}

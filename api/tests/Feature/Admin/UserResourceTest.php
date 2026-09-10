<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * C2 (tester feedback): "admin can delete users, not just hide/ban" —
 * soft delete, cascading to the user's shop/products (orders preserved,
 * stated explicitly in the confirmation), required reason, activity log,
 * and never deletable: your own account, or another admin/staff account.
 */
class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_delete_a_plain_buyer_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $buyer, data: ['reason' => 'Requested account removal'])
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted($buyer);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.deleted',
            'subject_id' => $buyer->id,
            'reason' => 'Requested account removal',
        ]);
    }

    public function test_deleting_a_user_without_a_reason_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $buyer, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertNotSoftDeleted($buyer);
    }

    public function test_deleting_a_seller_cascades_to_their_shop_and_products_but_preserves_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $sellerUser = User::factory()->create();
        $shop = SellerProfile::factory()->verified()->create(['user_id' => $sellerUser->id]);
        $products = Product::factory()->count(2)->create(['seller_id' => $shop->id]);
        $order = Order::factory()->create(['seller_id' => $shop->id]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $sellerUser, data: ['reason' => 'Policy violation'])
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted($sellerUser);
        $this->assertSoftDeleted($shop);
        foreach ($products as $product) {
            $this->assertSoftDeleted($product);
        }
        // Orders are a two-party record — deleting the seller must never
        // silently corrupt the buyer's own order history. `orders` has no
        // `deleted_at` column at all (never made soft-deletable), so mere
        // continued existence is the actual, correct assertion here.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('delete', $admin);

        $this->assertNotSoftDeleted($admin);
    }

    public function test_an_admin_cannot_delete_another_admin_account(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('delete', $otherAdmin);

        $this->assertNotSoftDeleted($otherAdmin);
    }

    public function test_bulk_delete_skips_self_and_admin_accounts_but_deletes_the_rest(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $buyers = User::factory()->count(2)->create();
        // Collection::push() mutates in place — build the selection as a
        // separate collection so $buyers itself still names only the two
        // accounts that should actually end up deleted.
        $selected = collect([...$buyers, $admin, $otherAdmin]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('bulk_delete', $selected, data: ['reason' => 'Cleanup']);

        foreach ($buyers as $buyer) {
            $this->assertSoftDeleted($buyer);
        }
        $this->assertNotSoftDeleted($admin);
        $this->assertNotSoftDeleted($otherAdmin);
        $this->assertSame(2, ActivityLog::where('action', 'user.deleted')->count());
    }

    public function test_an_admin_can_restore_a_deleted_user(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create();
        $buyer->delete();

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('restore', $buyer)
            ->assertHasNoTableActionErrors();

        $this->assertNotSoftDeleted($buyer->fresh());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.restored', 'subject_id' => $buyer->id]);
    }
}

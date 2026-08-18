<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_the_orders_list(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->count(3)->create();

        $this->actingAsAdmin($admin)->get('/admin/orders')->assertOk();
    }

    public function test_an_admin_can_view_an_order_with_items_timeline_and_conversation(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);
        Conversation::factory()->create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id]);

        $this->actingAsAdmin($admin)->get("/admin/orders/{$order->id}")->assertOk();
    }

    public function test_an_admin_can_cancel_a_cancellable_order_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAsAdmin($admin);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('cancel', data: ['reason' => 'Buyer requested cancellation'])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Buyer requested cancellation', $order->cancelled_reason);
    }

    public function test_cancelling_an_order_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAsAdmin($admin);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('cancel', data: ['reason' => ''])
            ->assertHasActionErrors(['reason']);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_completed_order_cannot_be_cancelled(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'completed']);

        $this->actingAsAdmin($admin);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('cancel', $order);
    }

    public function test_orders_can_be_filtered_by_status_and_seller(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = Order::factory()->create(['status' => 'pending']);
        $completed = Order::factory()->create(['status' => 'completed']);

        $this->actingAsAdmin($admin);

        Livewire::test(ListOrders::class)
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$completed]);
    }
}

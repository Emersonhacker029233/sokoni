<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BoltDeliveries\Pages\ListBoltDeliveries;
use App\Filament\Resources\BoltDeliveries\Pages\ViewBoltDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/** Admin > Commerce > Bolt deliveries: fee against Bolt's receipt, the ride, and the logged admin actions. */
class BoltDeliveryResourceTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_admins_see_the_list_and_a_delivery_with_its_bolt_ride_and_costs(): void
    {
        $admin = User::factory()->admin()->create();
        $this->boltReceipt = ['amount' => 7000, 'currency_code' => 'TZS', 'invoice_url' => 'https://invoice.example/1', 'fare_breakdown' => []];
        $job = $this->boltMoves($this->bookedJob(), 'COMPLETED');

        $this->actingAsAdmin($admin)->get('/admin/bolt-deliveries')->assertOk()->assertSee($job->order->code);
        $this->actingAsAdmin($admin)->get("/admin/bolt-deliveries/{$job->id}")
            ->assertOk()
            ->assertSee('1001')
            ->assertSee('Seyf Driver')
            ->assertSee('https://invoice.example/1');

        $this->actingAsAdmin($admin);
        Livewire::test(ListBoltDeliveries::class)
            ->filterTable('overrun', true)
            ->assertCanSeeTableRecords([$job]);
    }

    public function test_an_admin_records_a_fee_paid_outside_the_app_and_the_ride_is_booked(): void
    {
        $admin = User::factory()->admin()->create();
        $job = $this->acceptAsSeller($this->placeDispatchOrder());
        $this->markReady($job->order);

        $this->actingAsAdmin($admin);
        Livewire::test(ViewBoltDelivery::class, ['record' => $job->id])
            ->callAction('recordFeePaid', data: ['reference' => 'QK7H2X9ABC'])
            ->assertHasNoActionErrors();

        $job->refresh();
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame('pending_driver', $job->status, 'the shop was already ready, so Bolt was booked straight away');
        $event = $job->events()->where('to_status', 'awaiting_ready')->sole();
        $this->assertSame('admin', $event->actor_role);
        $this->assertSame('manual:QK7H2X9ABC', $event->meta['reference']);
    }

    public function test_an_admin_marks_a_refund_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $job = $this->paidJob();
        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/cancel")->assertOk();

        $this->actingAsAdmin($admin);
        Livewire::test(ViewBoltDelivery::class, ['record' => $job->id])
            ->callAction('markRefunded', data: ['reference' => 'REFUND-1'])
            ->assertHasNoActionErrors();

        $this->assertSame('refunded', $job->fresh()->refund_status);
    }

    public function test_staff_can_look_but_not_record_money(): void
    {
        $staff = User::factory()->staff()->create();
        $job = $this->acceptAsSeller($this->placeDispatchOrder());

        $this->actingAsAdmin($staff)->get("/admin/bolt-deliveries/{$job->id}")->assertOk();
        $this->actingAsAdmin($staff);
        Livewire::test(ViewBoltDelivery::class, ['record' => $job->id])->assertActionHidden('recordFeePaid');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BoltDeliveries\Pages\ListBoltDeliveries;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Returns\ReturnsTestHelpers;
use Tests\TestCase;

/**
 * Admin > Commerce > Bolt deliveries and Admin > Trust & Safety > Returns:
 * driver, tracking link, Bolt's estimate against its receipt, refunds,
 * the timeline, and the driver's live position while a ride runs.
 */
class BoltAdminLiveAndCostsTest extends TestCase
{
    use RefreshDatabase;
    use ReturnsTestHelpers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_a_running_delivery_shows_its_driver_tracking_link_estimate_and_live_position(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVING_WITH_CLIENT');
        $this->boltDriverAt($job->provider_ref, -6.80123, 39.27891);

        $this->actingAsAdmin($this->admin)->get("/admin/bolt-deliveries/{$job->id}")->assertOk()
            ->assertSee('Seyf Driver')
            ->assertSee('T123ABC')
            ->assertSee('https://bolt.eu/track/abc')
            ->assertSee('TSh 5,200 – 6,400')
            ->assertSee('Driver position now')
            ->assertSee('-6.80123, 39.27891')
            ->assertSee('https://www.openstreetmap.org/?mlat=-6.801230&amp;mlon=39.278910', false);

        $this->actingAsAdmin($this->admin)->get('/admin/bolt-deliveries')->assertOk()
            ->assertSee('Seyf Driver · T123ABC')
            ->assertSee('5,200 – 6,400');
        Livewire::test(ListBoltDeliveries::class)->assertTableActionVisible('trackOnBolt', $job);
    }

    public function test_a_finished_delivery_shows_estimate_against_receipt_and_asks_bolt_nothing_live(): void
    {
        $this->boltReceipt = ['amount' => 5900, 'currency_code' => 'TZS', 'invoice_url' => 'https://invoice.example/9', 'fare_breakdown' => []];
        $job = $this->boltMoves($this->bookedJob(), 'COMPLETED');
        $calls = $this->boltLocationCalls;

        $this->actingAsAdmin($this->admin)->get("/admin/bolt-deliveries/{$job->id}")->assertOk()
            ->assertSee('TSh 5,200 – 6,400')
            ->assertSee('6,400')
            ->assertSee('5,900')
            ->assertSee('500')
            ->assertSee('https://invoice.example/9')
            ->assertSee('Only while a Bolt driver is on the ride')
            ->assertSee('pending_driver → delivered', false); // the timeline (the sandbox skipped straight to COMPLETED)
        $this->assertSame($calls, $this->boltLocationCalls);

        Livewire::test(ListBoltDeliveries::class)->assertTableActionHidden('trackOnBolt', $job);
    }

    public function test_a_return_shows_the_ride_back_costs_refunds_and_its_whole_timeline(): void
    {
        $return = $this->requestedReturn('wrong_item');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk();
        $ride = (string) $return->fresh()->provider_ref;

        $this->boltState($ride, 'DRIVING_WITH_CLIENT');
        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->boltDriverAt($ride, -6.8050, 39.2770);

        // While the item is on its way: driver, Bolt's link and where the car is.
        $this->actingAsAdmin($this->admin)->get("/admin/returns/{$return->id}")->assertOk()
            ->assertSee('Seyf Driver')
            ->assertSee('https://bolt.eu/track/abc')
            ->assertSee('-6.80500, 39.27700')
            ->assertSee('Bolt driver collected the item');

        $this->boltReceipt = ['amount' => 5500, 'currency_code' => 'TZS', 'fare_breakdown' => []];
        $this->boltState($ride, 'COMPLETED');
        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/refunded", ['note' => 'M-Pesa 20,000'])->assertOk();

        $this->actingAsAdmin($this->admin);
        Livewire::test(ViewReturnRequest::class, ['record' => $return->id])->callAction('hideContent')->assertHasNoActionErrors();

        $page = $this->actingAsAdmin($this->admin)->get("/admin/returns/{$return->id}")->assertOk();
        $page->assertSee('TSh 5,200 – 6,400')
            ->assertSee('5,500')
            ->assertSee('900')
            ->assertSee('Confirmed by the shop')
            ->assertSee('Owed by the shop to Sokoni')
            ->assertSee('Only while the ride runs')
            ->assertSeeInOrder([
                'Requested by the buyer: Wrong item',
                'Decided by the shop',
                'Bolt ride back booked (ride '.$ride.')',
                'Bolt driver collected the item',
                'Item back at the shop',
                'Shop confirmed the refund',
                'Admin '.$this->admin->name.': return.hidden',
            ]);

        $this->actingAsAdmin($this->admin)->get('/admin/returns')->assertOk()->assertSee('Refunded')->assertSee('Seyf Driver · T123ABC');
    }
}

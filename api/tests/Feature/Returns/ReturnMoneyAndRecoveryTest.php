<?php

namespace Tests\Feature\Returns;

use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ends of a return that earlier tests didn't reach: a ride fee the
 * buyer paid for a ride that never carried the item, and a ride back that
 * fails with the item already in the car.
 */
class ReturnMoneyAndRecoveryTest extends TestCase
{
    use RefreshDatabase;
    use ReturnsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
    }

    /** "Changed my mind", accepted by the shop, the buyer's Pay now started. */
    private function acceptedBuyerPays(bool $confirmPayment = true): ReturnRequest
    {
        $return = $this->requestedReturn('changed_mind');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/pay", [
            'channel' => 'mpesa', 'msisdn' => '+255754123456', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(202);
        if ($confirmPayment) {
            $this->travel(9)->seconds();
            $this->artisan('delivery:tick')->assertSuccessful();
            $this->assertSame('paid', $return->fresh()->fee_status);
        }

        return $return->fresh();
    }

    public function test_a_paid_ride_back_the_buyer_withdraws_is_owed_back_and_an_admin_records_the_refund(): void
    {
        $return = $this->acceptedBuyerPays();
        $buyer = $return->buyer;

        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/withdraw")->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.fee_status', 'refund_pending');

        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin)->get('/admin/returns')->assertOk()->assertSee('Refund ride fee');
        $this->actingAsAdmin($admin);
        Livewire::test(ListReturnRequests::class)->filterTable('needs_admin', true)->assertCanSeeTableRecords([$return]);

        Livewire::test(ViewReturnRequest::class, ['record' => $return->id])
            ->assertActionHidden('setPayer')
            ->callAction('markRideFeeRefunded', data: ['reference' => 'MP-RF-001'])
            ->assertHasNoActionErrors();
        $this->assertSame('refunded', $return->fresh()->fee_status);
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['title'] === __('returns.push.buyer_ride_fee_refunded.title', [], $buyer->preferredLocale())));
        Livewire::test(ListReturnRequests::class)->filterTable('needs_admin', true)->assertCanNotSeeTableRecords([$return]);
    }

    public function test_a_payment_that_lands_after_the_buyer_withdrew_is_owed_back_not_kept(): void
    {
        $return = $this->acceptedBuyerPays(confirmPayment: false);
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/withdraw")->assertOk()
            ->assertJsonPath('data.fee_status', 'unpaid');

        // The mobile-money prompt the buyer had already approved is confirmed afterwards.
        $this->travel(9)->seconds();
        $this->artisan('delivery:tick')->assertSuccessful();

        $return->refresh();
        $this->assertSame('closed', $return->status);
        $this->assertSame('refund_pending', $return->fee_status);
        $this->assertFalse(
            $this->pushes->to($return->buyer)->contains(fn ($p) => $p['title'] === __('returns.push.buyer_fee_paid.title', [], $return->buyer->preferredLocale())),
            'no "book your pickup" push for a return that is over',
        );
    }

    public function test_the_shop_refunding_before_the_driver_collects_cancels_the_ride_and_owes_back_the_fee(): void
    {
        $return = $this->acceptedBuyerPays();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk()->assertJsonPath('data.ride.status', 'pending_driver');

        $this->asSeller($return)->postJson("/api/returns/{$return->id}/refunded", ['note' => 'Buyer brought it in person'])->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        $return->refresh();
        $this->assertContains((string) $return->provider_ref, $this->boltCancelled, 'no driver sent for nothing');
        $this->assertSame('refund_pending', $return->fee_status);
    }

    public function test_a_ride_fee_that_carried_the_item_is_not_refunded(): void
    {
        $return = $this->acceptedBuyerPays();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk();
        $ride = (string) $return->fresh()->provider_ref;

        foreach (['DRIVER_ON_ROUTE_TO_CLIENT', 'DRIVING_WITH_CLIENT', 'COMPLETED'] as $state) {
            $this->boltState($ride, $state);
            $this->travel(11)->seconds();
            $this->artisan('delivery:track')->assertSuccessful();
        }
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/refunded")->assertOk();

        $this->assertSame('paid', $return->fresh()->fee_status);
    }

    public function test_a_ride_back_that_ends_with_the_item_in_the_car_goes_to_sokoni_and_both_sides_hear(): void
    {
        $return = $this->requestedReturn('wrong_item');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk();
        $ride = (string) $return->fresh()->provider_ref;

        foreach (['DRIVER_ON_ROUTE_TO_CLIENT', 'DRIVING_WITH_CLIENT', 'CANCELLED'] as $state) {
            $this->boltState($ride, $state);
            $this->travel(11)->seconds();
            $this->artisan('delivery:track')->assertSuccessful();
        }

        $return->refresh();
        $this->assertSame('return_in_transit', $return->status);
        $this->assertSame('failed', $return->ride_status);
        $this->assertTrue($return->rideFailedInTransit());
        $this->assertTrue($return->needsAdmin());
        $this->assertSame('owed_by_seller', $return->fee_status, 'the ride ran: nothing to refund');

        $buyer = $return->buyer;
        $seller = $return->shop->user;
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['title'] === __('returns.push.buyer_ride_failed_in_transit.title', [], $buyer->preferredLocale())));
        $this->assertTrue($this->pushes->to($seller)->contains(fn ($p) => $p['title'] === __('returns.push.seller_ride_failed_in_transit.title', [], $seller->preferredLocale())));
        $this->asBuyer($return)->getJson("/api/returns/{$return->id}")
            ->assertJsonPath('data.handled_by_support', true)
            ->assertJsonPath('data.actions.can_book_ride', false)
            ->assertJsonPath('data.actions.can_withdraw', false);

        $this->actingAsAdmin(User::factory()->admin()->create())->get('/admin/returns')->assertOk()->assertSee('Item stuck with Bolt');
        Livewire::test(ListReturnRequests::class)->filterTable('needs_admin', true)->assertCanSeeTableRecords([$return]);
    }
}

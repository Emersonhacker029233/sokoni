<?php

namespace Tests\Feature\Returns;

use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Returns end to end (docs/delivery/RETURNS.md), with the Bolt sandbox faked. */
class ReturnFlowTest extends TestCase
{
    use RefreshDatabase;
    use ReturnsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
    }

    public function test_wrong_item_the_shop_pays_the_ride_back_and_refunds(): void
    {
        $order = $this->deliveredOrder('3d');
        $buyer = $order->buyer;
        $seller = $order->seller->user;

        // The order screen offers the return, with the policy seen at checkout.
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$order->id}")
            ->assertJsonPath('data.return.policy.code', '3d')
            ->assertJsonPath('data.return.policy.label', __('returns.policy.3d'))
            ->assertJsonPath('data.return.policy.notes', 'Unused, in the original box.')
            ->assertJsonPath('data.return.can_request', true)
            ->assertJsonPath('data.return.request', null);

        $response = $this->requestReturn($order, 'wrong_item', photos: 3)->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.reason', 'wrong_item')
            ->assertJsonPath('data.payer', 'seller')
            ->assertJsonCount(3, 'data.photos');
        $return = ReturnRequest::query()->findOrFail($response->json('data.id'));
        $this->assertSame(3, $return->photos()->count());
        Storage::disk('local')->assertExists($return->photos->first()->path);
        $this->assertTrue($this->pushes->to($seller)->contains(fn ($p) => ($p['data']['type'] ?? null) === 'return_status' && ($p['data']['return_id'] ?? null) == $return->id));

        // The shop sees it on the same order screen and accepts.
        $this->actingAs($seller)->getJson("/api/delivery/orders/{$order->id}")
            ->assertJsonPath('data.return.request.status', 'requested')
            ->assertJsonPath('data.return.request.comment', 'It is the blue one, I ordered black.')
            ->assertJsonPath('data.return.request.actions.can_accept', true);
        $this->actingAs($seller)->postJson("/api/returns/{$return->id}/accept", ['note' => 'Sorry about that'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.fee', self::FEE)
            ->assertJsonPath('data.fee_status', 'owed_by_seller')
            ->assertJsonPath('data.ride.status', 'quoted');

        // The buyer books the pickup straight away: nothing to pay.
        $this->actingAs($buyer)->getJson("/api/returns/{$return->id}")
            ->assertJsonPath('data.actions.can_pay', false)
            ->assertJsonPath('data.actions.can_book_ride', true);
        $this->actingAs($buyer)->postJson("/api/returns/{$return->id}/book")
            ->assertOk()
            ->assertJsonPath('data.status', 'return_in_transit')
            ->assertJsonPath('data.ride.status', 'pending_driver');

        $ride = end($this->boltCreated);
        $this->assertSame(['lat' => self::DROP_LAT, 'lng' => self::DROP_LNG], $ride['stops'][0], 'collected from the customer');
        $this->assertSame(['lat' => self::SHOP_LAT, 'lng' => self::SHOP_LNG], $ride['stops'][1], 'taken to the shop');
        $this->assertSame($buyer->name, $ride['user']['name'], 'the customer is the rider');
        $this->assertStringContainsString("RETURN of order {$order->code}", $ride['note_to_driver']);

        // Bolt drives it back.
        $this->boltState('1002', 'DRIVER_ON_ROUTE_TO_CLIENT');
        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->actingAs($buyer)->getJson("/api/returns/{$return->id}")
            ->assertJsonPath('data.ride.driver.name', 'Seyf Driver')
            ->assertJsonPath('data.ride.tracking_url', 'https://bolt.eu/track/abc');

        $this->boltReceipt = ['amount' => 5500, 'currency_code' => 'TZS', 'fare_breakdown' => []];
        $this->boltState('1002', 'COMPLETED');
        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();
        $return->refresh();
        $this->assertSame('received', $return->status);
        $this->assertSame(5500.0, $return->final_cost);
        $this->assertTrue($this->pushes->to($seller)->contains(fn ($p) => $p['title'] === __('returns.push.seller_received.title', [], $seller->preferredLocale())));

        // "Item received, refund done".
        $this->actingAs($seller)->postJson("/api/returns/{$return->id}/refunded", ['note' => 'Refunded TSh 20,000 by M-Pesa'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');
        $this->assertNotNull($return->fresh()->refunded_at);
        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$order->id}")
            ->assertJsonPath('data.return.request.status', 'refunded');
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['title'] === __('returns.push.buyer_refunded.title', [], $buyer->preferredLocale())));
    }

    public function test_changed_mind_the_buyer_pays_the_ride_back_through_pay_now(): void
    {
        $return = $this->requestedReturn('changed_mind');
        $this->assertSame('buyer', $return->payer);

        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk()
            ->assertJsonPath('data.fee_status', 'unpaid');
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertStatus(409)->assertJsonPath('code', 'ride_not_bookable');

        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/pay", [
            'channel' => 'mpesa', 'msisdn' => '+255754123456', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(202)->assertJsonPath('data.payment.attempt.status', 'pending')
            ->assertJsonPath('data.payment.attempt.amount', self::FEE);

        $payment = Payment::query()->where('purpose', 'return_fee')->sole();
        $this->assertSame($return->id, $payment->return_request_id);
        $this->assertNull($payment->delivery_job_id);

        $this->travel(9)->seconds();
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertSame('paid', $return->fresh()->fee_status);

        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk()->assertJsonPath('data.status', 'return_in_transit');
    }

    public function test_the_shop_rejects_with_a_reason_the_buyer_disputes_and_sokoni_decides(): void
    {
        $return = $this->requestedReturn('not_as_described');

        $this->asSeller($return)->postJson("/api/returns/{$return->id}/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/reject", ['reason' => 'The photo shows it as described'])
            ->assertOk()->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision.note', 'The photo shows it as described');

        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/dispute", ['comment' => 'The size label says M, I ordered L'])
            ->assertOk()->assertJsonPath('data.status', 'disputed')->assertJsonPath('data.handled_by_support', true);

        // Sokoni support overrides the shop.
        $admin = User::factory()->admin()->create();
        app(ReturnService::class)->accept($return, $admin, 'admin', 'Label shows M; the shop sent the wrong size');
        $return->refresh();
        $this->assertSame('accepted', $return->status);
        $this->assertSame('admin', $return->decided_role);
        $this->assertSame('owed_by_seller', $return->fee_status);
        $this->assertSame('The photo shows it as described', $return->decision_note, "the shop's note is kept");
    }

    public function test_the_shop_has_48_hours_then_sokoni_support_takes_over(): void
    {
        $return = $this->requestedReturn();

        $this->travel(47)->hours();
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertNull($return->fresh()->escalated_at);

        $this->travel(2)->hours();
        $this->artisan('delivery:tick')->expectsOutput('escalated: 1')->assertSuccessful();
        $return->refresh();
        $this->assertNotNull($return->escalated_at);
        $this->assertSame('seller_no_response', $return->escalation_reason);
        $this->assertTrue($return->needsAdmin());
        $this->asBuyer($return)->getJson("/api/returns/{$return->id}")->assertJsonPath('data.handled_by_support', true);

        // The shop can still answer late; the admin queue simply shows it resolved.
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
    }

    public function test_requests_outside_the_policy_are_refused(): void
    {
        $none = $this->deliveredOrder('none');
        $this->requestReturn($none)->assertStatus(409)->assertJsonPath('code', 'no_return_policy');

        $day = $this->deliveredOrder('24h');
        $this->travel(25)->hours();
        $this->requestReturn($day)->assertStatus(409)->assertJsonPath('code', 'return_window_closed');
        $this->actingAs($day->buyer)->getJson("/api/delivery/orders/{$day->id}")->assertJsonPath('data.return.can_request', false);
    }

    public function test_only_delivered_bolt_orders_once_by_their_buyer(): void
    {
        $job = $this->bookedJob(shop: $this->shop(['return_policy' => '7d']));
        $this->requestReturn($job->order)->assertStatus(409)->assertJsonPath('code', 'return_not_available');

        $order = $this->deliveredOrder('7d');
        $this->actingAs(User::factory()->create())->post("/api/orders/{$order->id}/returns", [], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->requestReturn($order)->assertCreated();
        $this->requestReturn($order)->assertStatus(409)->assertJsonPath('code', 'return_exists');
    }

    public function test_reason_and_one_to_three_photos_are_required(): void
    {
        $order = $this->deliveredOrder();

        $this->requestReturn($order, 'too_expensive')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->requestReturn($order, photos: 0)->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->requestReturn($order, photos: 4)->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->actingAs($order->buyer)->post("/api/orders/{$order->id}/returns", [
            'reason' => 'damaged', 'photos' => [UploadedFile::fake()->create('virus.exe', 10)],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    }

    public function test_the_buyer_can_withdraw_before_the_item_is_on_its_way(): void
    {
        $return = $this->requestedReturn();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/withdraw")->assertOk()
            ->assertJsonPath('data.status', 'closed')->assertJsonPath('data.closed_reason', 'withdrawn');

        $booked = $this->requestedReturn();
        $this->asSeller($booked)->postJson("/api/returns/{$booked->id}/accept")->assertOk();
        $this->asBuyer($booked)->postJson("/api/returns/{$booked->id}/book")->assertOk();
        $this->asBuyer($booked)->postJson("/api/returns/{$booked->id}/withdraw")->assertStatus(409)->assertJsonPath('code', 'cannot_withdraw');
    }

    public function test_a_pickup_that_fails_before_collection_can_be_booked_again(): void
    {
        $return = $this->requestedReturn();
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk();
        $rideId = $return->fresh()->provider_ref;

        $this->boltState($rideId, 'NO_DRIVER_FOUND');
        $this->travel(11)->seconds();
        $this->artisan('delivery:track')->assertSuccessful();
        $this->asBuyer($return)->getJson("/api/returns/{$return->id}")
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.ride.status', 'no_driver_found')
            ->assertJsonPath('data.actions.can_book_ride', true);

        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk()->assertJsonPath('data.status', 'return_in_transit');
        $this->assertNotSame($rideId, $return->fresh()->provider_ref);
    }

    public function test_bolt_not_serving_the_buyer_tells_them_to_arrange_it_with_the_shop(): void
    {
        $return = $this->requestedReturn();
        $this->boltFailNext('estimate', 422, 'RIDE_BOOKER_API_AREA_NOT_SERVICED');

        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk()
            ->assertJsonPath('data.ride.status', 'unavailable');
        $this->asBuyer($return)->getJson("/api/returns/{$return->id}")->assertJsonPath('data.actions.can_book_ride', false);
        $buyer = $return->buyer;
        $this->assertTrue($this->pushes->to($buyer)->contains(fn ($p) => $p['title'] === __('returns.push.buyer_accepted_no_ride.title', [], $buyer->preferredLocale())));

        // The item comes back in person; the shop still confirms the refund.
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/refunded")->assertOk()->assertJsonPath('data.status', 'refunded');
    }

    public function test_a_bolt_webhook_for_the_ride_back_moves_the_return(): void
    {
        $return = $this->requestedReturn();
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/book")->assertOk();
        $rideId = $return->fresh()->provider_ref;
        $this->boltState($rideId, 'COMPLETED');

        $this->withServerVariables(['REMOTE_ADDR' => '18.197.164.122'])
            ->withHeaders(['Authorization' => 'Bearer hook-secret'])
            ->postJson('/api/webhooks/bolt', ['ride_id' => (int) $rideId, 'type' => 'ON_DEMAND', 'state' => 'COMPLETED'])
            ->assertOk()->assertJson(['handled' => true]);

        $this->assertSame('received', $return->fresh()->status);
        $this->assertSame('delivered', $this->deliveryJobOf($return)->status, 'the original delivery is untouched');
    }

    public function test_each_side_only_does_its_own_part(): void
    {
        $return = $this->requestedReturn();

        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/accept")->assertForbidden()->assertJsonPath('code', 'seller_must_decide');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/withdraw")->assertForbidden()->assertJsonPath('code', 'not_your_return');
        $this->actingAs(User::factory()->create())->getJson("/api/returns/{$return->id}")->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson("/api/returns/{$return->id}/accept")->assertNotFound();
    }
}

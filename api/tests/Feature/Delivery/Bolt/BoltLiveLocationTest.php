<?php

namespace Tests\Feature\Delivery\Bolt;

use App\Models\User;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltDriverLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/**
 * The buyer's live map: GET /api/delivery/orders/{order}/location, backed
 * by Bolt's ride-location endpoint (faked), cached per ride.
 */
class BoltLiveLocationTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    private function locationUrl(int $orderId): string
    {
        return "/api/delivery/orders/{$orderId}/location";
    }

    public function test_the_buyer_sees_the_driver_from_assignment_until_the_ride_completes(): void
    {
        $job = $this->bookedJob();
        $buyer = $job->order->buyer;
        $url = $this->locationUrl($job->order_id);

        // Bolt is still searching: the ride runs, but there is no driver to show and Bolt isn't asked.
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.status', 'pending_driver')
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.reason', 'no_driver_yet')
            ->assertJsonPath('data.poll_seconds', 5);
        $this->assertSame(0, $this->boltLocationCalls);

        $job = $this->boltMoves($job, 'DRIVER_ON_ROUTE_TO_CLIENT');
        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700, now()->getTimestamp());
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.status', 'driver_assigned')
            ->assertJsonPath('data.location.lat', -6.815)
            ->assertJsonPath('data.location.lng', 39.27)
            ->assertJsonPath('data.location.bearing', 90)
            ->assertJsonPath('data.location.recorded_at', now()->toIso8601String())
            ->assertJsonPath('data.stale', false)
            ->assertJsonPath('data.reason', null)
            // Bolt's own ETA to the pickup, which for a delivery is the shop.
            ->assertJsonPath('data.eta', ['seconds' => 180, 'to' => 'shop', 'source' => 'bolt'])
            ->assertJsonPath('data.pickup', ['lat' => self::SHOP_LAT, 'lng' => self::SHOP_LNG])
            ->assertJsonPath('data.dropoff', ['lat' => self::DROP_LAT, 'lng' => self::DROP_LNG]);

        $job = $this->boltMoves($job, 'DRIVING_WITH_CLIENT');
        $this->boltDriverAt($job->provider_ref, self::DROP_LAT - 0.009, self::DROP_LNG); // about 1 km from the customer
        $data = $this->actingAs($buyer)->getJson($url)->assertOk()->json('data');
        $this->assertSame('on_the_way', $data['status']);
        $this->assertSame(['to' => 'customer', 'source' => 'estimate'], array_intersect_key($data['eta'], ['to' => 1, 'source' => 1]));
        $this->assertEqualsWithDelta(253, $data['eta']['seconds'], 15, '1 km x 1.4 road factor at 20 km/h');

        $calls = $this->boltLocationCalls;
        $this->boltMoves($job, 'COMPLETED');
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.reason', 'ride_not_active')
            ->assertJsonPath('data.eta', null);
        $this->assertSame($calls, $this->boltLocationCalls, 'a finished ride is never asked for its driver');
    }

    public function test_many_polls_make_at_most_one_bolt_call_per_ride_every_five_seconds(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $buyer = $job->order->buyer;
        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700);

        foreach (range(1, 5) as $_) {
            $this->actingAs($buyer)->getJson($this->locationUrl($job->order_id))->assertOk()->assertJsonPath('data.location.lat', -6.815);
        }
        $this->assertSame(1, $this->boltLocationCalls);

        $this->boltDriverAt($job->provider_ref, -6.8140, 39.2710);
        $this->travel(6)->seconds();
        $this->actingAs($buyer)->getJson($this->locationUrl($job->order_id))->assertOk()
            ->assertJsonPath('data.location.lat', -6.814)
            ->assertJsonPath('data.stale', false);
        $this->assertSame(2, $this->boltLocationCalls);
    }

    public function test_unable_to_locate_driver_shows_the_last_known_position_as_stale(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $buyer = $job->order->buyer;
        $url = $this->locationUrl($job->order_id);

        // No fix yet and nothing known: an answer, not an error.
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.reason', 'driver_not_located');

        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700);
        $this->travel(6)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertJsonPath('data.location.lat', -6.815)->assertJsonPath('data.stale', false);

        unset($this->boltLocations[$job->provider_ref]); // Bolt loses the driver's fix
        $this->travel(6)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.location.lat', -6.815)
            ->assertJsonPath('data.stale', true)
            ->assertJsonPath('data.reason', 'driver_not_located');
    }

    public function test_a_rate_limited_bolt_is_left_alone_for_its_retry_after_without_sleeping(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $buyer = $job->order->buyer;
        $url = $this->locationUrl($job->order_id);
        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700);
        $this->actingAs($buyer)->getJson($url)->assertJsonPath('data.stale', false);

        Sleep::fake();
        $this->boltFailNext('location', 429, null, [], ['Retry-After' => '20']);
        $this->travel(6)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.reason', 'rate_limited')
            ->assertJsonPath('data.location.lat', -6.815)
            ->assertJsonPath('data.stale', true);
        $this->assertSame(2, $this->boltLocationCalls);
        Sleep::assertNeverSlept();

        // Inside Bolt's 20 seconds: no call, even though the 5-second cache has expired.
        $this->travel(10)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertOk()->assertJsonPath('data.reason', 'rate_limited');
        $this->assertSame(2, $this->boltLocationCalls);

        $this->travel(11)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertOk()->assertJsonPath('data.reason', null)->assertJsonPath('data.stale', false);
        $this->assertSame(3, $this->boltLocationCalls);
    }

    public function test_bolt_errors_are_answered_with_the_last_known_position(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $buyer = $job->order->buyer;
        $url = $this->locationUrl($job->order_id);
        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700);
        $this->actingAs($buyer)->getJson($url)->assertOk();

        Sleep::fake();
        $this->boltFailNext('location', 503);
        $this->travel(6)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.reason', 'unavailable')
            ->assertJsonPath('data.location.lat', -6.815)
            ->assertJsonPath('data.stale', true);
        $this->assertSame(2, $this->boltLocationCalls, 'one attempt per poll: the app asks again in 5 seconds');
        Sleep::assertNeverSlept();
    }

    public function test_a_ride_bolt_already_ended_stops_the_map_on_the_next_sync(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVING_WITH_CLIENT');
        $buyer = $job->order->buyer;
        $url = $this->locationUrl($job->order_id);
        $this->boltDriverAt($job->provider_ref, -6.8000, 39.2790);
        $this->actingAs($buyer)->getJson($url)->assertJsonPath('data.status', 'on_the_way');

        // Bolt finishes the ride; Sokoni's 10-second read hasn't happened yet.
        $this->boltState($job->provider_ref, 'COMPLETED');
        $this->travel(6)->seconds();
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.reason', 'ride_ended')
            ->assertJsonPath('data.stale', true);

        $this->travel(5)->seconds(); // the order screen's 10-second ride-details window reopens
        $this->actingAs($buyer)->getJson($url)->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_only_the_buyer_sees_where_the_driver_is(): void
    {
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700);
        $url = $this->locationUrl($job->order_id);

        $this->actingAs($job->order->seller->user)->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['phone' => '+255719000111']))->getJson($url)->assertForbidden();
        $this->assertSame(0, $this->boltLocationCalls);

        // Outside the test list: as if the route didn't exist.
        config(['delivery.test_phones' => '+255700000001']);
        $this->actingAs($job->order->buyer)->getJson($url)->assertNotFound();
        config(['delivery.test_phones' => '', 'delivery.enabled' => false]);
        $this->actingAs($job->order->buyer)->getJson($url)->assertNotFound();
    }

    public function test_the_order_screen_says_when_the_map_has_a_driver_and_shows_bolts_receipt(): void
    {
        $job = $this->bookedJob();
        $buyer = $job->order->buyer;
        $show = "/api/delivery/orders/{$job->order_id}";

        $this->actingAs($buyer)->getJson($show)->assertJsonPath('data.live_location', false)->assertJsonPath('data.final_cost', null);
        $job = $this->boltMoves($job, 'DRIVER_ON_ROUTE_TO_CLIENT');
        $this->actingAs($buyer)->getJson($show)->assertJsonPath('data.live_location', true);

        $this->boltReceipt = ['amount' => 5900, 'currency_code' => 'TZS', 'fare_breakdown' => []];
        $this->boltMoves($job, 'COMPLETED');
        $this->actingAs($buyer)->getJson($show)
            ->assertJsonPath('data.live_location', false)
            ->assertJsonPath('data.fee', self::FEE)
            ->assertJsonPath('data.payment.delivery_fee', self::FEE)
            ->assertJsonPath('data.final_cost', 5900);

        $seller = $this->actingAs($job->order->seller->user)->getJson($show)->json('data');
        $this->assertArrayNotHasKey('final_cost', $seller);
        $this->assertArrayNotHasKey('live_location', $seller);
    }

    public function test_the_location_path_is_configurable(): void
    {
        config(['delivery.bolt.paths.location' => 'rides/driver-location']);
        $job = $this->boltMoves($this->bookedJob(), 'DRIVER_ON_ROUTE_TO_CLIENT');
        $this->boltDriverAt($job->provider_ref, -6.8150, 39.2700);

        $this->actingAs($job->order->buyer)->getJson($this->locationUrl($job->order_id))->assertJsonPath('data.location.lat', -6.815);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_starts_with($r->url(), 'https://node.bolt.eu/ride-booker-api-sandbox/reseller/v1/sandbox/rides/driver-location?')
            && str_contains($r->url(), "ride_id={$job->provider_ref}")
            && str_contains($r->url(), 'uuid=sandbox-uuid-1'));
    }

    public function test_the_location_body_is_read_in_every_likely_shape(): void
    {
        $this->travelTo(now()->startOfMinute());

        $flat = BoltDriverLocation::fromArray(['latitude' => '-6.81', 'longitude' => '39.27', 'updated_at' => now()->subSeconds(3)->toIso8601String()]);
        $this->assertSame([-6.81, 39.27], [$flat->lat, $flat->lng]);
        $this->assertTrue($flat->recordedAt->equalTo(now()->subSeconds(3)));

        $nested = BoltDriverLocation::fromArray(['ride_id' => 1001, 'location' => ['lat' => -6.8, 'lng' => 39.2, 'heading' => -90], 'timestamp' => now()->getTimestampMs()]);
        $this->assertSame([-6.8, 39.2], [$nested->lat, $nested->lng]);
        $this->assertSame(270.0, $nested->toArray()['bearing']);
        $this->assertSame(now()->getTimestamp(), $nested->recordedAt->getTimestamp(), 'milliseconds are read as milliseconds');

        $deep = BoltDriverLocation::fromArray(['data' => ['driver_location' => ['lat' => -6.7, 'lon' => 39.1]]]);
        $this->assertSame([-6.7, 39.1], [$deep->lat, $deep->lng]);
        $this->assertNull($deep->recordedAt);

        foreach ([[], ['lat' => 0, 'lng' => 0], ['lat' => 120, 'lng' => 39], ['location' => ['lat' => 'x', 'lng' => 'y']]] as $bad) {
            try {
                BoltDriverLocation::fromArray($bad);
                $this->fail('accepted '.json_encode($bad));
            } catch (BoltApiException $e) {
                $this->assertSame('location', $e->operation);
            }
        }
    }
}

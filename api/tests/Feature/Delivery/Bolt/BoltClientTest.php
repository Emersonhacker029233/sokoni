<?php

namespace Tests\Feature\Delivery\Bolt;

use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/** The Ride Booker HTTP layer on its own: auth, URLs, parsing, retries and error headers. */
class BoltClientTest extends TestCase
{
    use FakesBolt;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    public function test_token_is_fetched_with_client_credentials_and_cached_until_two_minutes_before_expiry(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 600, 'token_type' => 'Bearer']),
            self::SANDBOX.'/rides/estimations*' => Http::response(['estimations' => []]),
        ]);
        $client = $this->boltClient();

        $client->estimate($this->stops());
        $client->estimate($this->stops());

        Http::assertSentCount(3); // one token + two estimates
        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL
            && $r['grant_type'] === 'client_credentials'
            && $r['scope'] === 'ridebooker:api'
            && $r['client_id'] === 'test-client'
            && $r['client_secret'] === 'test-secret');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'estimations') && $r->hasHeader('Authorization', 'Bearer tok-1'));

        // 8 minutes later (10 min lifetime - 2 min margin) a new token is fetched.
        $this->travel(8 * 60 + 1)->seconds();
        $client->estimate($this->stops());
        Http::assertSentCount(5);
    }

    public function test_sandbox_calls_use_the_sandbox_prefix_and_uuid_and_live_calls_do_not(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            '*' => Http::response(['estimations' => []]),
        ]);

        $this->boltClient()->estimate($this->stops());
        Http::assertSent(fn (Request $r) => $r->url() === self::SANDBOX.'/rides/estimations?uuid=sandbox-uuid-1');

        $this->boltClient(['env' => 'live', 'sandbox_uuid' => null])->estimate($this->stops());
        Http::assertSent(fn (Request $r) => $r->url() === 'https://node.bolt.eu/ride-booker-api/reseller/v1/rides/estimations');
    }

    public function test_estimates_parse_both_fare_id_spellings_and_the_amount_range_text(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides/estimations*' => Http::response(['estimations' => [
                ['Fare_id' => 'F-1', 'vehicle_category' => 'comfort', 'eta' => 200, 'price' => ['amount' => '10.50-12.50', 'currency_code' => 'eur']],
                ['fare_id' => 'F-2', 'vehicle_category' => 'bolt', 'eta' => null, 'price' => ['minimum_amount' => 4000, 'maximum_amount' => 5000, 'currency_code' => 'TZS']],
            ]]),
        ]);

        [$comfort, $bolt] = $this->boltClient()->estimate($this->stops());

        $this->assertSame('F-1', $comfort->fareId);
        $this->assertSame(10.5, $comfort->minAmount);
        $this->assertSame(12.5, $comfort->maxAmount);
        $this->assertSame('EUR', $comfort->currency);
        $this->assertTrue($comfort->isBookable());
        $this->assertSame(13, $comfort->customerFee());
        $this->assertFalse($bolt->isBookable(), 'a null ETA means the category cannot take a ride now');
        $this->assertArrayNotHasKey('fare_id', $comfort->toArray());
    }

    public function test_create_sends_fare_rider_stops_and_note_and_returns_the_ride_id(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides?uuid=*' => Http::response(['ride_id' => 1001]),
        ]);

        $rideId = $this->boltClient()->createRide('FARE-X', ['name' => 'Duka la Juma', 'phone' => '+255754000111'], $this->stops(), 'Order SK1');

        $this->assertSame('1001', $rideId);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/rides?')
            && $r['fare_id'] === 'FARE-X'
            && $r['user'] === ['name' => 'Duka la Juma', 'phone' => '+255754000111']
            && $r['stops'] === $this->stops()
            && $r['note_to_driver'] === 'Order SK1');
    }

    public function test_ride_details_read_the_driver_and_tracking_link(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides?*' => Http::response($this->detailsBody('1001', 'DRIVER_ON_ROUTE_TO_CLIENT')),
        ]);

        $details = $this->boltClient()->rideDetails('1001');

        $this->assertSame('DRIVER_ON_ROUTE_TO_CLIENT', $details->state);
        $this->assertSame([
            'name' => 'Seyf Driver', 'phone' => '+255700667788', 'plate' => 'T123ABC', 'model' => 'Toyota IST',
            'photo_url' => 'https://driver-picture-url/', 'tracking_url' => 'https://bolt.eu/track/abc', 'eta_seconds' => 180,
        ], $details->driver());
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), 'ride_id=1001') && str_contains($r->url(), 'uuid=sandbox-uuid-1'));
    }

    public function test_429_and_500_are_retried_with_backoff_then_succeed(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides/estimations*' => Http::sequence()
                ->push('', 429, ['Retry-After' => '1', 'x-bolt-error-code' => 'RATE'])
                ->push('', 500, ['x-bolt-error-code' => 'INTERNAL'])
                ->push(['estimations' => []]),
        ]);

        $this->assertSame([], $this->boltClient()->estimate($this->stops()));

        Sleep::assertSleptTimes(2);
        // Bolt's Retry-After (1 s) first, then exponential backoff with jitter (800-950 ms).
        Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 1000);
        Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 800 && $duration->totalMilliseconds <= 950);
    }

    public function test_a_persistent_500_throws_with_the_bolt_headers_and_logs_them(): void
    {
        Log::spy();
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides/estimations*' => Http::response('', 500, [
                'x-bolt-error-code' => 'RIDE_BOOKER_API_INTERNAL',
                'x-bolt-error-message' => 'Boom',
                'x-bolt-tracking' => 'trk-123',
            ]),
        ]);

        try {
            $this->boltClient()->estimate($this->stops());
            $this->fail('expected a BoltApiException');
        } catch (BoltApiException $e) {
            $this->assertSame(500, $e->status);
            $this->assertSame('RIDE_BOOKER_API_INTERNAL', $e->errorCode);
            $this->assertSame('Boom', $e->errorMessage);
            $this->assertSame('trk-123', $e->tracking);
            $this->assertTrue($e->isTransient());
        }

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($message, '[bolt] estimate failed')
            && $context['x-bolt-error-code'] === 'RIDE_BOOKER_API_INTERNAL'
            && $context['x-bolt-error-message'] === 'Boom'
            && $context['x-bolt-tracking'] === 'trk-123')->times(3);
    }

    public function test_a_400_is_not_retried_and_carries_validation_errors(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides?uuid=*' => Http::response(
                ['validation_errors' => [['error' => 'Validation error', 'property' => 'user', 'children' => [['error' => 'Not string', 'property' => 'phone']]]]],
                400,
                ['x-bolt-error-code' => 'RIDE_BOOKER_API_INVALID_FARE_ID'],
            ),
        ]);

        try {
            $this->boltClient()->createRide('OLD', ['name' => 'Shop', 'phone' => '+255754000111'], $this->stops(), 'n');
            $this->fail('expected a BoltApiException');
        } catch (BoltApiException $e) {
            $this->assertTrue($e->isFareRejected());
            $this->assertFalse($e->isTransient());
            $this->assertSame('user', $e->validationErrors[0]['property']);
        }
        Sleep::assertNeverSlept();
    }

    public function test_a_401_fetches_a_new_token_once(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::sequence()
                ->push(['access_token' => 'old', 'expires_in' => 600])
                ->push(['access_token' => 'new', 'expires_in' => 600]),
            self::SANDBOX.'/rides/receipt*' => Http::sequence()
                ->push('', 401)
                ->push(['ride_id' => 1001, 'amount' => 6500, 'currency_code' => 'TZS', 'invoice_url' => 'https://inv', 'fare_breakdown' => [['type' => 'ride_fee', 'amount' => 6500]]]),
        ]);

        $receipt = $this->boltClient()->receipt('1001');

        $this->assertSame(6500.0, $receipt->amount);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'receipt') && $r->hasHeader('Authorization', 'Bearer new'));
    }

    public function test_dropped_connections_are_retried_then_reported_as_transient(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            self::SANDBOX.'/rides?*' => Http::failedConnection('timed out'),
        ]);

        try {
            $this->boltClient()->rideDetails('1001');
            $this->fail('expected a BoltApiException');
        } catch (BoltApiException $e) {
            $this->assertNull($e->status);
            $this->assertTrue($e->isTransient());
        }
        Sleep::assertSleptTimes(2);
    }

    public function test_nothing_is_sent_without_credentials(): void
    {
        Http::fake();

        $this->expectException(BoltApiException::class);
        try {
            $this->boltClient(['client_id' => null])->estimate($this->stops());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_cancel_and_sandbox_controls_hit_the_documented_paths(): void
    {
        Http::fake([
            self::TOKEN_URL => $this->tokenResponse(),
            '*' => Http::response(['uuid' => 'new-sandbox']),
        ]);
        $client = $this->boltClient();

        $client->cancelRide('1001');
        $client->sandboxSetState('1001', 'completed');
        $this->assertSame('new-sandbox', $client->sandboxCreate());

        Http::assertSent(fn (Request $r) => $r->url() === self::SANDBOX.'/rides/cancel?uuid=sandbox-uuid-1' && $r['ride_id'] === 1001);
        Http::assertSent(fn (Request $r) => $r->url() === self::SANDBOX.'/rides/control/state?uuid=sandbox-uuid-1' && $r['ride_state'] === 'COMPLETED');
        Http::assertSent(fn (Request $r) => $r->url() === self::SANDBOX.'/control/create');
    }

    private function boltClient(array $overrides = []): BoltClient
    {
        return new BoltClient([...$this->boltConfig(), ...$overrides]);
    }
}

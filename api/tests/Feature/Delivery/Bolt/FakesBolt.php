<?php

namespace Tests\Feature\Delivery\Bolt;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A stateful fake of the Bolt Ride Booker sandbox for feature tests.
 *
 * - `$this->boltEstimates` is what POST /rides/estimations answers (fresh
 *   fare_ids every call, like Bolt).
 * - Each POST /rides books ride 1001, 1002, ... in state SEARCHING.
 * - `$this->boltState('1001', 'COMPLETED')` moves a ride, as the sandbox
 *   control endpoint would.
 * - `$this->boltFailNext('create', 500, 'RIDE_BOOKER_API_...')` makes the
 *   next call of that kind fail with Bolt's error headers.
 */
trait FakesBolt
{
    protected const TOKEN_URL = 'https://oidc.bolt.eu/token';

    protected const SANDBOX = 'https://node.bolt.eu/ride-booker-api-sandbox/reseller/v1/sandbox';

    /** @var array<string, string> ride_id => Bolt state */
    protected array $boltRides = [];

    /** @var list<array> estimation rows WITHOUT fare_id (added per call) */
    protected array $boltEstimates = [];

    /** @var array<string, list<array{status: int, code: ?string, body: array}>> */
    protected array $boltFailures = [];

    /** @var list<string> every fare_id handed out, in order */
    protected array $boltFareIds = [];

    /** @var list<array{fare_id: string, user: array, stops: array, note_to_driver: string}> */
    protected array $boltCreated = [];

    /** @var list<string> */
    protected array $boltCancelled = [];

    protected ?array $boltReceipt = null;

    protected int $boltNextRide = 1001;

    /**
     * What the ride-location endpoint answers per ride while it is active.
     * No entry = Bolt can't locate the driver (UNABLE_TO_LOCATE_DRIVER).
     *
     * @var array<string, array>
     */
    protected array $boltLocations = [];

    protected int $boltLocationCalls = 0;

    protected function boltConfig(): array
    {
        return [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'env' => 'sandbox',
            'sandbox_uuid' => 'sandbox-uuid-1',
            'webhook_token' => 'hook-secret',
            'webhook_ips' => '',
            'token_url' => self::TOKEN_URL,
            'scope' => 'ridebooker:api',
            'live_base_url' => 'https://node.bolt.eu/ride-booker-api',
            'sandbox_base_url' => 'https://node.bolt.eu/ride-booker-api-sandbox',
            'paths' => ['cancel' => 'rides/cancel', 'location' => 'rides/location'],
            'location_cache_seconds' => 5,
            'timeout_seconds' => 5,
            'connect_timeout_seconds' => 2,
            'max_attempts' => 3,
            'poll_seconds' => 10,
            'max_booking_attempts' => 5,
        ];
    }

    protected function tokenResponse()
    {
        return Http::response(['access_token' => 'test-token', 'expires_in' => 600, 'token_type' => 'Bearer', 'scope' => 'ridebooker:api']);
    }

    protected function stops(): array
    {
        return [['lat' => -6.8161, 'lng' => 39.2803], ['lat' => -6.7924, 'lng' => 39.2083]];
    }

    protected function detailsBody(string $rideId, string $state): array
    {
        $assigned = ! in_array($state, ['SEARCHING', 'NO_DRIVER_FOUND'], true);

        return array_filter([
            'ride_id' => (int) $rideId,
            'state' => $state,
            'eta_to_client' => $assigned ? 180 : null,
            'tracking_url' => $assigned ? 'https://bolt.eu/track/abc' : null,
            'vehicle' => $assigned ? [
                'car_reg_number' => 'T123ABC',
                'car_model' => 'Toyota IST',
                'car_transport_licence_number' => 'LC-4455',
                'driver_name' => 'Seyf Driver',
                'driver_phone' => '+255700667788',
                'driver_picture' => 'https://driver-picture-url/',
            ] : null,
            'payment_method' => 'business',
            'created' => '2026-11-17T10:00:00.000Z',
        ], fn ($v) => $v !== null);
    }

    /** Bolt switched on for the whole app (no allowlist), sandbox credentials, fake Bolt answering. */
    protected function enableBolt(array $config = []): void
    {
        config([
            'delivery.enabled' => true,
            'delivery.test_phones' => '',
            'delivery.bolt' => [...$this->boltConfig(), ...$config],
        ]);
        $this->boltEstimates = [
            ['vehicle_category' => 'bolt_send', 'eta' => 240, 'price' => ['minimum_amount' => 5200, 'maximum_amount' => 6400, 'currency_code' => 'TZS']],
            ['vehicle_category' => 'comfort', 'eta' => 300, 'price' => ['minimum_amount' => 7000, 'maximum_amount' => 8800, 'currency_code' => 'TZS']],
            ['vehicle_category' => 'boda', 'eta' => null, 'price' => ['minimum_amount' => 2000, 'maximum_amount' => 2500, 'currency_code' => 'TZS']],
        ];
        $this->fakeBolt();
    }

    protected function boltState(string $rideId, string $state): void
    {
        $this->boltRides[$rideId] = $state;
    }

    protected function boltFailNext(string $operation, int $status, ?string $code = null, array $body = [], array $headers = []): void
    {
        $this->boltFailures[$operation][] = ['status' => $status, 'code' => $code, 'body' => $body, 'headers' => $headers];
    }

    /** Where the ride's driver is, as Bolt's location endpoint would say. */
    protected function boltDriverAt(string $rideId, float $lat, float $lng, ?int $timestamp = null): void
    {
        $this->boltLocations[$rideId] = ['lat' => $lat, 'lng' => $lng, 'bearing' => 90, 'timestamp' => $timestamp ?? now()->getTimestamp()];
    }

    protected function fakeBolt(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            $path = parse_url($url, PHP_URL_PATH) ?? '';

            // Other fakes (Dalipay) answer their own hosts.
            if (! str_ends_with((string) parse_url($url, PHP_URL_HOST), 'bolt.eu')) {
                return null;
            }

            if ($url === self::TOKEN_URL) {
                return $this->tokenResponse();
            }

            $operation = match (true) {
                str_ends_with($path, '/rides/estimations') => 'estimate',
                str_ends_with($path, '/rides/receipt') => 'receipt',
                str_ends_with($path, '/rides/cancel') => 'cancel',
                str_ends_with($path, '/rides/control/state') => 'state',
                str_ends_with($path, '/sandbox/control/create') => 'sandbox_create',
                preg_match('#/rides/[a-z_-]*location$#', $path) === 1 => 'location',
                str_ends_with($path, '/rides') && $request->method() === 'POST' => 'create',
                str_ends_with($path, '/rides') => 'details',
                default => 'unknown',
            };

            if ($operation === 'location') {
                $this->boltLocationCalls++;
            }

            $this->boltFailures[$operation] ??= [];
            if (($failure = array_shift($this->boltFailures[$operation])) !== null) {
                return Http::response($failure['body'], $failure['status'], array_filter([
                    'x-bolt-error-code' => $failure['code'],
                    'x-bolt-error-message' => $failure['code'] ? 'fake '.$failure['code'] : null,
                    'x-bolt-tracking' => 'trk-'.$operation,
                    ...($failure['headers'] ?? []),
                ]));
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return match ($operation) {
                'estimate' => Http::response(['estimations' => array_map(function (array $row) {
                    $fare = 'FARE-'.(count($this->boltFareIds) + 1);
                    $this->boltFareIds[] = $fare;

                    return ['fare_id' => $fare, ...$row];
                }, $this->boltEstimates)]),
                'create' => (function () use ($request) {
                    $id = (string) $this->boltNextRide++;
                    $this->boltRides[$id] = 'SEARCHING';
                    $this->boltCreated[] = $request->data();

                    return Http::response(['ride_id' => (int) $id]);
                })(),
                'details' => isset($this->boltRides[(string) ($query['ride_id'] ?? '')])
                    ? Http::response($this->detailsBody((string) $query['ride_id'], $this->boltRides[(string) $query['ride_id']]))
                    : Http::response('', 404, ['x-bolt-error-code' => 'RIDE_BOOKER_API_MISSING_ORDER']),
                'receipt' => $this->boltReceipt !== null
                    ? Http::response(['ride_id' => (int) ($query['ride_id'] ?? 0), ...$this->boltReceipt])
                    : Http::response('', 409, ['x-bolt-error-code' => 'RIDE_BOOKER_API_FARE_BREAKDOWN_UNAVAILABLE']),
                'cancel' => (function () use ($request) {
                    $id = (string) $request['ride_id'];
                    $this->boltCancelled[] = $id;
                    $this->boltRides[$id] = 'CLIENT_CANCELLED';

                    return Http::response([]);
                })(),
                'state' => (function () use ($request) {
                    $this->boltRides[(string) $request['ride_id']] = (string) $request['ride_state'];

                    return Http::response([]);
                })(),
                'sandbox_create' => Http::response(['uuid' => 'b1034a93-c5af-4fa1-a3b5-1bdb255f8967']),
                'location' => (function () use ($query) {
                    $id = (string) ($query['ride_id'] ?? '');
                    $state = $this->boltRides[$id] ?? null;

                    return match (true) {
                        $state === null => Http::response('', 404, ['x-bolt-error-code' => 'RIDE_BOOKER_API_MISSING_ORDER']),
                        ! in_array($state, ['SEARCHING', 'DRIVER_ON_ROUTE_TO_CLIENT', 'ARRIVED_AT_CLIENT', 'DRIVING_WITH_CLIENT'], true) => Http::response('', 409, ['x-bolt-error-code' => 'RIDE_BOOKER_API_INACTIVE_RIDE_REQUESTED']),
                        ! isset($this->boltLocations[$id]) => Http::response('', 409, ['x-bolt-error-code' => 'RIDE_BOOKER_API_UNABLE_TO_LOCATE_DRIVER']),
                        default => Http::response($this->boltLocations[$id]),
                    };
                })(),
                default => Http::response('', 404),
            };
        });
    }
}

<?php

namespace App\Services\Delivery\Bolt;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Bolt Ride Booker API (docs/delivery/BOLT.md), on-demand rides only.
 *
 * - Auth: OAuth2 client credentials at https://oidc.bolt.eu/token, scope
 *   ridebooker:api. The token lasts 10 minutes; it is cached and replaced
 *   2 minutes before it expires, and once more if Bolt answers 401.
 * - Retries: 429 and 5xx (and dropped connections) are retried with
 *   exponential backoff, honouring Retry-After. Creating a ride is safe to
 *   retry because its fare_id is single use: a retry of a create that did
 *   go through is refused by Bolt rather than booking twice.
 * - Every failure is logged with x-bolt-error-code, x-bolt-error-message
 *   and x-bolt-tracking, the three values Bolt support asks for.
 * - Sandbox (BOLT_ENV=sandbox): the same paths under /sandbox/, each with
 *   ?uuid=BOLT_SANDBOX_UUID. Live paths and base URLs are config values so
 *   they can be corrected without a code change (BOLT.md, "Confirm before
 *   going live").
 *
 * Credentials come from .env only (config/delivery.php). Nothing here is
 * ever logged with a token or secret.
 */
class BoltClient
{
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('delivery.bolt', []));
    }

    public function isSandbox(): bool
    {
        return ($this->config['env'] ?? 'sandbox') !== 'live';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['client_id'] ?? null)
            && filled($this->config['client_secret'] ?? null)
            && (! $this->isSandbox() || filled($this->config['sandbox_uuid'] ?? null));
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $stops  pickup first, drop-off last
     * @return list<BoltEstimate>
     */
    public function estimate(array $stops): array
    {
        $body = $this->send('estimate', 'POST', 'rides/estimations', json: ['stops' => $this->stops($stops)]);

        return array_values(array_filter(array_map(
            fn ($row) => BoltEstimate::fromArray($row),
            is_array($body['estimations'] ?? null) ? $body['estimations'] : [],
        )));
    }

    /**
     * Books the ride. $stops must be exactly the stops the fare_id was
     * estimated for, or Bolt refuses it.
     *
     * @param  array{name: string, phone: string}  $rider
     * @param  list<array{lat: float, lng: float}>  $stops
     * @return string Bolt's ride_id
     */
    public function createRide(string $fareId, array $rider, array $stops, string $noteToDriver): string
    {
        $body = $this->send('create', 'POST', 'rides', json: [
            'fare_id' => $fareId,
            'user' => ['name' => $rider['name'], 'phone' => $rider['phone']],
            'stops' => $this->stops($stops),
            'note_to_driver' => $noteToDriver,
        ]);

        $rideId = $body['ride_id'] ?? null;
        if (! is_scalar($rideId) || (string) $rideId === '') {
            throw BoltApiException::malformed('create', 'no ride_id');
        }

        return (string) $rideId;
    }

    /** Bolt's fair-use rule: at most every 10 seconds per ride. */
    public function rideDetails(string $rideId): BoltRideDetails
    {
        $body = $this->send('details', 'GET', 'rides', query: ['ride_id' => $rideId]);

        return BoltRideDetails::fromArray(['ride_id' => $rideId, ...$body]);
    }

    /**
     * The driver's position (Bolt: "ride location", active rides only,
     * lightweight, may be called every second). One attempt and no backoff
     * sleep: the buyer's app asks again in a few seconds anyway, and a web
     * request must not hang on a rate-limited Bolt. The path is a config
     * value because Bolt's PDF doesn't give it (BOLT_LOCATION_PATH).
     */
    public function driverLocation(string $rideId): BoltDriverLocation
    {
        $body = $this->send(
            'location',
            'GET',
            (string) ($this->config['paths']['location'] ?? 'rides/location'),
            query: ['ride_id' => $rideId],
            attempts: 1,
        );

        return BoltDriverLocation::fromArray($body);
    }

    public function receipt(string $rideId): BoltReceipt
    {
        return BoltReceipt::fromArray($this->send('receipt', 'GET', 'rides/receipt', query: ['ride_id' => $rideId]));
    }

    public function cancelRide(string $rideId): void
    {
        $this->send('cancel', 'POST', (string) ($this->config['paths']['cancel'] ?? 'rides/cancel'), json: ['ride_id' => $this->rideIdValue($rideId)]);
    }

    /** Sandbox only: a new isolated sandbox. Returns its uuid (put it in BOLT_SANDBOX_UUID). */
    public function sandboxCreate(): string
    {
        $body = $this->send('sandbox create', 'POST', 'control/create', sandboxPrefix: 'sandbox/', withUuid: false);
        $uuid = $body['uuid'] ?? null;
        if (! is_string($uuid) || $uuid === '') {
            throw BoltApiException::malformed('sandbox create', 'no uuid');
        }

        return $uuid;
    }

    /** Sandbox only: moves a ride to $state, as a real driver would. */
    public function sandboxSetState(string $rideId, string $state): void
    {
        $this->send('sandbox state', 'POST', 'rides/control/state', json: ['ride_id' => $this->rideIdValue($rideId), 'ride_state' => strtoupper($state)]);
    }

    public function sandboxDestroy(string $uuid): void
    {
        $this->send('sandbox destroy', 'POST', 'control/destroy', json: ['uuid' => $uuid], sandboxPrefix: 'sandbox/', withUuid: false);
    }

    /**
     * @param  'GET'|'POST'  $method
     */
    private function send(
        string $operation,
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        ?string $sandboxPrefix = null,
        bool $withUuid = true,
        ?int $attempts = null,
    ): array {
        if ($withUuid && ! $this->isConfigured()) {
            throw BoltApiException::notConfigured($operation);
        }

        $url = $this->url($path, $sandboxPrefix);
        if ($this->isSandbox() && $withUuid) {
            $query['uuid'] = (string) $this->config['sandbox_uuid'];
        }

        $maxAttempts = max(1, $attempts ?? (int) ($this->config['max_attempts'] ?? 3));
        $retriedAuth = false;

        for ($attempt = 1; ; $attempt++) {
            try {
                $request = Http::withToken($this->token())
                    ->acceptJson()
                    ->connectTimeout(max(1, (int) ($this->config['connect_timeout_seconds'] ?? 5)))
                    ->timeout(max(1, (int) ($this->config['timeout_seconds'] ?? 15)));

                $response = $method === 'GET'
                    ? $request->get($url, $query)
                    : $request->post($query === [] ? $url : $url.'?'.http_build_query($query), $json ?? (object) []);
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    $this->backoff($attempt);

                    continue;
                }
                $exception = BoltApiException::connection($operation, $e);
                Log::warning('[bolt] '.$exception->getMessage(), $exception->context());
                throw $exception;
            }

            if ($response->successful()) {
                return (array) ($response->json() ?? []);
            }

            $exception = BoltApiException::fromResponse($operation, $response);
            Log::warning("[bolt] {$operation} failed (attempt {$attempt})", $exception->context());

            if ($response->status() === 401 && ! $retriedAuth) {
                // The cached token died early (rotated credentials): get a new one once.
                $retriedAuth = true;
                Cache::forget($this->tokenCacheKey());

                continue;
            }
            if (in_array($response->status(), BoltApiException::TRANSIENT_STATUSES, true) && $attempt < $maxAttempts) {
                $this->backoff($attempt, $response);

                continue;
            }

            throw $exception;
        }
    }

    /**
     * Cached until 2 minutes before Bolt's expiry (10 minutes today), so a
     * request never goes out with a token that dies mid-flight.
     */
    private function token(): string
    {
        $key = $this->tokenCacheKey();
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (! filled($this->config['client_id'] ?? null) || ! filled($this->config['client_secret'] ?? null)) {
            throw BoltApiException::notConfigured('token');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(max(1, (int) ($this->config['connect_timeout_seconds'] ?? 5)))
                ->timeout(max(1, (int) ($this->config['timeout_seconds'] ?? 15)))
                ->post((string) ($this->config['token_url'] ?? 'https://oidc.bolt.eu/token'), [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'grant_type' => 'client_credentials',
                    'scope' => $this->config['scope'] ?? 'ridebooker:api',
                ]);
        } catch (ConnectionException $e) {
            $exception = BoltApiException::connection('token', $e);
            Log::warning('[bolt] '.$exception->getMessage(), $exception->context());
            throw $exception;
        }

        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token) || $token === '') {
            $exception = $response->failed()
                ? BoltApiException::fromResponse('token', $response)
                : BoltApiException::malformed('token', 'no access_token');
            Log::error('[bolt] could not get an access token', $exception->context());
            throw $exception;
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 600);
        Cache::put($key, $token, max(30, $expiresIn - 120));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'bolt:token:'.sha1(($this->config['client_id'] ?? '').'|'.($this->config['scope'] ?? ''));
    }

    private function url(string $path, ?string $sandboxPrefix = null): string
    {
        $base = rtrim((string) ($this->isSandbox()
            ? ($this->config['sandbox_base_url'] ?? 'https://node.bolt.eu/ride-booker-api-sandbox')
            : ($this->config['live_base_url'] ?? 'https://node.bolt.eu/ride-booker-api')), '/');
        $prefix = $sandboxPrefix ?? ($this->isSandbox() ? 'sandbox/' : '');

        return "{$base}/reseller/v1/{$prefix}".ltrim($path, '/');
    }

    /** @param list<array{lat: float, lng: float}> $stops */
    private function stops(array $stops): array
    {
        return array_map(fn (array $s) => ['lat' => round((float) $s['lat'], 7), 'lng' => round((float) $s['lng'], 7)], array_values($stops));
    }

    /** Bolt's ride ids are numbers in every example. */
    private function rideIdValue(string $rideId): int|string
    {
        return ctype_digit($rideId) ? (int) $rideId : $rideId;
    }

    /** 0.4 s, 0.8 s, 1.6 s ... capped at 4 s, or Bolt's Retry-After (capped at 5 s) so a web request never hangs. */
    private function backoff(int $attempt, ?Response $response = null): void
    {
        $retryAfter = (int) ($response?->header('Retry-After') ?: 0);
        $ms = $retryAfter > 0
            ? min(5000, $retryAfter * 1000)
            : min(4000, 400 * (2 ** ($attempt - 1))) + random_int(0, 150);

        Sleep::for($ms)->milliseconds();
    }
}

<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltRideBooker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The Bolt driver's live position for the buyer's tracking map
 * (GET /api/delivery/orders/{order}/location) and the admin's live link.
 *
 * Bolt is asked at most once per ride per `location_cache_seconds` (5 s),
 * however many phones poll: the first caller in a window passes a cache
 * gate and asks Bolt, everyone else reads the cached answer. Failures are
 * part of the answer, never an error page:
 *
 *  - UNABLE_TO_LOCATE_DRIVER: Bolt has no fix right now. The last known
 *    position (kept 15 minutes) is shown, marked stale.
 *  - 429: Bolt is rate limiting. The gate stays shut for Retry-After
 *    (5 to 30 s) and the last known position is shown.
 *  - INACTIVE_RIDE_REQUESTED / MISSING_ORDER: the ride ended at Bolt before
 *    Sokoni's 10-second tracker noticed (`reason: ride_ended`). The next
 *    poll's ride-details sync moves the delivery on and `active` turns false.
 *  - anything else (5xx, timeout, malformed body, not configured): last
 *    known position, stale.
 */
class DriverLocationService
{
    private const LAST_KNOWN_SECONDS = 900;

    public function __construct(private readonly BoltRideBooker $bolt) {}

    /**
     * What the buyer's map needs: the driver's point, whether it is fresh,
     * the delivery status and an ETA.
     *
     * `active` is true while the Bolt ride runs (searching through on the
     * way): the app keeps polling. It turns false once the ride is over
     * (delivered, cancelled, failed), which is the app's signal to stop.
     * The driver's point itself is only asked for from "driver assigned"
     * on; while Bolt is (again) searching, `reason` is `no_driver_yet`.
     */
    public function forJob(DeliveryJob $job): array
    {
        $base = [
            'status' => $job->status,
            'active' => $job->isBolt() && $job->isRideActive(),
            'poll_seconds' => self::pollSeconds(),
            'pickup' => ['lat' => $job->pickup_lat, 'lng' => $job->pickup_lng],
            'dropoff' => ['lat' => $job->dropoff_lat, 'lng' => $job->dropoff_lng],
        ];

        if (! $job->hasLiveLocation()) {
            return [
                ...$base,
                'location' => null,
                'stale' => false,
                'reason' => $base['active'] ? 'no_driver_yet' : 'ride_not_active',
                'eta' => null,
                'checked_at' => null,
            ];
        }

        $answer = $this->forRide((string) $job->provider_ref);
        $location = $answer['location'];

        return [
            ...$base,
            'location' => $location,
            'stale' => $answer['stale'],
            'reason' => $answer['reason'],
            'eta' => $this->eta($job, $location),
            'checked_at' => $answer['checked_at'],
        ];
    }

    /**
     * The driver of Bolt ride $rideId, cached and gated.
     *
     * @return array{location: ?array, stale: bool, reason: ?string, checked_at: ?string}
     */
    public function forRide(string $rideId): array
    {
        $key = self::key($rideId);
        $answer = Cache::get($key);
        if (! is_array($answer) && Cache::add("{$key}:gate", true, self::cacheSeconds())) {
            $answer = $this->ask($rideId);
        }
        // Another request is asking Bolt this very moment: show what we had.
        $answer = is_array($answer) ? $answer : ['location' => null, 'reason' => null, 'checked_at' => null];

        $fresh = $answer['location'] ?? null;
        $location = $fresh ?? Cache::get("{$key}:last");

        return [
            'location' => is_array($location) ? $location : null,
            'stale' => $fresh === null && is_array($location),
            'reason' => $answer['reason'] ?? null,
            'checked_at' => $answer['checked_at'] ?? null,
        ];
    }

    public static function pollSeconds(): int
    {
        return max(3, (int) config('delivery.tracking.location_poll_seconds', 5));
    }

    private function ask(string $rideId): array
    {
        $key = self::key($rideId);
        $ttl = self::cacheSeconds();
        $now = now()->toIso8601String();

        try {
            $location = $this->bolt->client()->driverLocation($rideId)->toArray();
            $answer = ['location' => $location, 'reason' => null, 'checked_at' => $now];
            Cache::put("{$key}:last", $location, self::LAST_KNOWN_SECONDS);
        } catch (BoltApiException $e) {
            $reason = match (true) {
                $e->is('UNABLE_TO_LOCATE_DRIVER') => 'driver_not_located',
                $e->is('INACTIVE_RIDE_REQUESTED', 'MISSING_ORDER') => 'ride_ended',
                $e->status === 429 => 'rate_limited',
                default => 'unavailable',
            };
            $answer = ['location' => null, 'reason' => $reason, 'checked_at' => $now];

            if ($reason === 'rate_limited') {
                // Keep the gate shut for as long as Bolt asked (5 to 30 s).
                $ttl = min(30, max($ttl, (int) ($e->retryAfter ?? 0)));
                Cache::put("{$key}:gate", true, $ttl);
            }
            if ($reason === 'unavailable' && Cache::add("{$key}:logged", true, 60)) {
                Log::warning("[bolt] driver location for ride {$rideId} unavailable", $e->context());
            }
        }

        Cache::put($key, $answer, $ttl);

        return $answer;
    }

    /**
     * Before pickup: Bolt's own ETA to the shop (from the last ride-details
     * read, less the seconds since). With the parcel in the car Bolt gives
     * none, so: straight-line distance from the driver to the customer, x a
     * road factor, at an average city speed. Shown as "about N min".
     *
     * @return array{seconds: int, to: string, source: string}|null
     */
    private function eta(DeliveryJob $job, ?array $location): ?array
    {
        if ($job->status === 'driver_assigned') {
            $seconds = ((array) ($job->provider_driver ?? []))['eta_seconds'] ?? null;
            if (! is_numeric($seconds)) {
                return null;
            }
            $elapsed = $job->last_polled_at instanceof Carbon ? (int) $job->last_polled_at->diffInSeconds(now(), true) : 0;

            return ['seconds' => max(60, (int) $seconds - $elapsed), 'to' => 'shop', 'source' => 'bolt'];
        }

        if ($job->status === 'on_the_way' && $location !== null && $job->dropoff_lat !== null) {
            $km = Haversine::km((float) $location['lat'], (float) $location['lng'], (float) $job->dropoff_lat, (float) $job->dropoff_lng);
            $speed = max(5.0, (float) config('delivery.tracking.eta_speed_kmh', 20));
            $factor = max(1.0, (float) config('delivery.tracking.eta_road_factor', 1.4));

            return ['seconds' => max(60, (int) ceil($km * $factor / $speed * 3600)), 'to' => 'customer', 'source' => 'estimate'];
        }

        return null;
    }

    private static function cacheSeconds(): int
    {
        return max(1, (int) config('delivery.bolt.location_cache_seconds', 5));
    }

    private static function key(string $rideId): string
    {
        return 'bolt:location:'.sha1($rideId);
    }
}

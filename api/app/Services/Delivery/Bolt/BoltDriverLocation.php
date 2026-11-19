<?php

namespace App\Services\Delivery\Bolt;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * GET ride location: where the Bolt driver is right now. Bolt's PDF says
 * the endpoint exists, is lightweight and works only while the ride is
 * active, but leaves its fields to the Swagger file, which wasn't
 * available. So this reads the usual shapes defensively:
 *
 *   { lat, lng }  { latitude, longitude }  { location: { lat, lng } }
 *   { driver_location: {...} }  { data: {...} }
 *
 * with an optional time (`timestamp`, `updated_at`, `recorded_at`, ...;
 * unix seconds, unix milliseconds or ISO 8601) and bearing. A body without
 * a usable coordinate is a malformed answer, never a pin at 0,0.
 */
final class BoltDriverLocation
{
    private const CONTAINERS = ['location', 'driver_location', 'driverLocation', 'position', 'coordinates', 'data'];

    private const TIME_KEYS = ['timestamp', 'updated_at', 'recorded_at', 'location_timestamp', 'location_updated_at', 'last_updated', 'time'];

    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?Carbon $recordedAt = null,
        public readonly ?float $bearing = null,
    ) {}

    public static function fromArray(array $body): self
    {
        foreach (self::candidates($body) as $candidate) {
            $lat = self::number($candidate['lat'] ?? $candidate['latitude'] ?? null);
            $lng = self::number($candidate['lng'] ?? $candidate['lon'] ?? $candidate['long'] ?? $candidate['longitude'] ?? null);
            if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
                continue;
            }

            return new self(
                $lat,
                $lng,
                self::time($candidate) ?? self::time($body),
                self::number($candidate['bearing'] ?? $candidate['heading'] ?? $candidate['course'] ?? null),
            );
        }

        throw BoltApiException::malformed('location', 'no driver coordinates');
    }

    /** What the app and the cache get: no Bolt-specific shape leaks out. */
    public function toArray(): array
    {
        return [
            'lat' => round($this->lat, 6),
            'lng' => round($this->lng, 6),
            'bearing' => $this->bearing === null ? null : round(fmod($this->bearing + 360, 360), 1),
            'recorded_at' => $this->recordedAt?->toIso8601String(),
        ];
    }

    /** @return list<array> the body itself, then any nested object that may hold the point */
    private static function candidates(array $body): array
    {
        $found = [$body];
        foreach (self::CONTAINERS as $key) {
            if (is_array($body[$key] ?? null)) {
                $found[] = $body[$key];
                foreach (self::CONTAINERS as $inner) {
                    if (is_array($body[$key][$inner] ?? null)) {
                        $found[] = $body[$key][$inner];
                    }
                }
            }
        }

        return $found;
    }

    private static function time(array $source): ?Carbon
    {
        foreach (self::TIME_KEYS as $key) {
            $value = $source[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            try {
                if (is_numeric($value)) {
                    $seconds = (float) $value;
                    // Milliseconds since 1970 are 13 digits; seconds are 10.
                    $time = Carbon::createFromTimestamp($seconds > 1e11 ? $seconds / 1000 : $seconds);
                } elseif (is_string($value)) {
                    $time = Carbon::parse($value);
                } else {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }
            // A clock far in the future is a bad value, not a fresh point.
            if ($time->lte(now()->addMinutes(5)) && $time->year >= 2020) {
                return $time;
            }
        }

        return null;
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

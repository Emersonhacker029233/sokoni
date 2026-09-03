<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort server-side geocoding for the website's seller registration
 * form, which has no interactive map pin (no Google Maps key configured —
 * see BLOCKERS.md — and this deliberately doesn't reach for it even though
 * Part 2 of this same brief adopts OpenStreetMap for *display*: an
 * interactive click-to-drop-pin picker is a genuinely separate, bigger
 * piece of work than an address text field, out of proportion to what a
 * one-page registration form needs — see DECISIONS.md).
 *
 * Nominatim (OpenStreetMap's free geocoding API) needs no key and no
 * billing, matching this app's existing OSM choice, but its usage policy
 * requires a real identifying User-Agent and a max of ~1 request/second —
 * both satisfied here (this only ever runs once, at registration time, not
 * on a hot path). Never throws — a failed/slow/malformed lookup just means
 * the seller profile is created with a null lat/lng, exactly the same
 * "address text only, no map" state `SellerProfile::hasLocation()` and the
 * shop page already handle gracefully.
 */
class NominatimGeocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const TIMEOUT_SECONDS = 4;

    /** @return array{lat: float, lng: float}|null */
    public function geocode(string $address, string $district, string $region): ?array
    {
        $query = trim("{$address}, {$district}, {$region}, Tanzania");

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Sokoni/1.0 (+https://sokoni.co.tz; contact@sokoni.co.tz)',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(self::ENDPOINT, [
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'tz',
                    'q' => $query,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json(0);

            if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
                return null;
            }

            return ['lat' => (float) $result['lat'], 'lng' => (float) $result['lon']];
        } catch (\Throwable $e) {
            Log::info('Nominatim geocoding failed during seller registration, continuing without coordinates.', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

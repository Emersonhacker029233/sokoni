<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional road distance via OpenRouteService (bound only when
 * OPENROUTESERVICE_API_KEY is set). Any failure or timeout falls back to
 * straight-line pricing, so an outage never blocks an order.
 */
class OpenRouteServiceDistance implements RouteDistance
{
    public function __construct(
        private readonly string $apiKey,
        private readonly StraightLineDistance $fallback,
    ) {}

    public function between(float $fromLat, float $fromLng, float $toLat, float $toLng): DistanceResult
    {
        try {
            $response = Http::timeout(4)
                ->withHeaders(['Authorization' => $this->apiKey])
                ->get('https://api.openrouteservice.org/v2/directions/driving-car', [
                    'start' => "{$fromLng},{$fromLat}",
                    'end' => "{$toLng},{$toLat}",
                ]);

            $metres = $response->json('features.0.properties.summary.distance');
            if ($response->successful() && is_numeric($metres)) {
                return new DistanceResult(round(((float) $metres) / 1000, 2), 'road');
            }
            Log::warning('[delivery] OpenRouteService returned no distance', ['status' => $response->status()]);
        } catch (Throwable $e) {
            Log::warning('[delivery] OpenRouteService failed: '.$e->getMessage());
        }

        return $this->fallback->between($fromLat, $fromLng, $toLat, $toLng);
    }
}

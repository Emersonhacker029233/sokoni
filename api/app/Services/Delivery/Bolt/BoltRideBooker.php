<?php

namespace App\Services\Delivery\Bolt;

use App\Services\Delivery\DeliveryUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pricing and booking on Bolt for both directions: shop -> customer
 * deliveries and customer -> shop return rides. Always two stops.
 *
 * Rules from the Bolt docs that this class enforces:
 *  - The category is the cheapest one whose ETA is not null (null ETA =
 *    "rides for the category are not possible at the time").
 *  - A booking always asks Bolt for a FRESH estimate and creates the ride
 *    with that fare_id straight away. fare_ids expire after 5 minutes and
 *    are single use, so one is never cached, stored or reused. The only
 *    thing cached is the price shown at checkout, without its fare_id.
 *  - The create call sends exactly the stops that were estimated.
 */
class BoltRideBooker
{
    /** Checkout and order placement share one estimate for this long. */
    private const QUOTE_CACHE_SECONDS = 120;

    public function __construct(private readonly BoltClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function client(): BoltClient
    {
        return $this->client;
    }

    /**
     * The price for a trip, for checkout and for freezing the fee when the
     * shop accepts. Never includes a fare_id.
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     *
     * @throws DeliveryUnavailableException Bolt has no bookable category there (area not serviced, or no ETA)
     * @throws BoltApiException anything else (network, 5xx after retries, credentials)
     */
    public function quote(array $from, array $to, bool $fresh = false): BoltEstimate
    {
        $key = 'bolt:quote:'.implode(',', array_map(fn ($v) => number_format((float) $v, 4, '.', ''), [$from['lat'], $from['lng'], $to['lat'], $to['lng']]));
        if (! $fresh && is_array($cached = Cache::get($key))) {
            return new BoltEstimate(null, $cached['category'], $cached['eta_seconds'], $cached['min'], $cached['max'], $cached['currency']);
        }

        $best = $this->cheapest($from, $to);
        Cache::put($key, $best->toArray(), self::QUOTE_CACHE_SECONDS);

        return new BoltEstimate(null, $best->category, $best->etaSeconds, $best->minAmount, $best->maxAmount, $best->currency);
    }

    /**
     * Estimates again and books the cheapest bookable category at once.
     * If Bolt says the fare_id is invalid (expired, used, corrupted), it
     * estimates once more for a new one; it never resends an old fare_id.
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     * @param  array{name: string, phone: string}  $rider
     * @return array{ride_id: string, estimate: BoltEstimate}
     *
     * @throws DeliveryUnavailableException
     * @throws BoltApiException
     */
    public function book(array $from, array $to, array $rider, string $noteToDriver): array
    {
        $stops = [$from, $to];

        for ($round = 1; ; $round++) {
            $estimate = $this->cheapest($from, $to);
            try {
                $rideId = $this->client->createRide((string) $estimate->fareId, $rider, $stops, $noteToDriver);

                return ['ride_id' => $rideId, 'estimate' => $estimate];
            } catch (BoltApiException $e) {
                if ($e->isFareRejected() && $round < 2) {
                    Log::info('[bolt] fare_id refused, estimating again', $e->context());

                    continue;
                }
                if ($e->isNotServiced()) {
                    throw new DeliveryUnavailableException('area_not_serviced', __('delivery.errors.area_not_serviced'));
                }
                throw $e;
            }
        }
    }

    /**
     * @throws DeliveryUnavailableException
     * @throws BoltApiException
     */
    private function cheapest(array $from, array $to): BoltEstimate
    {
        try {
            $estimates = $this->client->estimate([$from, $to]);
        } catch (BoltApiException $e) {
            if ($e->isNotServiced()) {
                throw new DeliveryUnavailableException('area_not_serviced', __('delivery.errors.area_not_serviced'));
            }
            throw $e;
        }

        $bookable = array_values(array_filter($estimates, fn (BoltEstimate $e) => $e->isBookable()));
        if ($bookable === []) {
            throw new DeliveryUnavailableException('no_driver_available', __('delivery.errors.no_driver_available'));
        }

        usort($bookable, fn (BoltEstimate $a, BoltEstimate $b) => [$a->maxAmount, $a->minAmount ?? 0, $a->etaSeconds] <=> [$b->maxAmount, $b->minAmount ?? 0, $b->etaSeconds]);

        $best = $bookable[0];
        if (! $this->client->isSandbox() && $best->currency !== null && $best->currency !== 'TZS') {
            // A live account priced in another currency would charge customers the wrong amount.
            Log::error('[bolt] live estimate is not in TZS; refusing to price a delivery with it', $best->toArray());
            throw BoltApiException::malformed('estimate', "currency {$best->currency} is not TZS");
        }

        return $best;
    }
}

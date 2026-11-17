<?php

namespace App\Services\Delivery;

use App\Models\SellerProfile;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltEstimate;
use App\Services\Delivery\Bolt\BoltRideBooker;
use App\Support\DeliverySettings;

/**
 * The delivery price shown at checkout (GET /delivery/quote) and checked
 * again when the order is placed: Sokoni's own limits first (the shop has
 * a map location, the drop-off is within delivery_max_distance_km), then
 * Bolt's estimate for the trip. The binding fee is set when the shop
 * accepts (DeliveryJobService::quote).
 */
class DeliveryQuoter
{
    public function __construct(private readonly BoltRideBooker $bolt) {}

    /**
     * @return array{estimate: BoltEstimate, distance_km: float}
     *
     * @throws DeliveryUnavailableException shop_has_no_location | too_far | area_not_serviced | no_driver_available
     * @throws BoltApiException Bolt could not be asked (retry later)
     */
    public function quote(SellerProfile $shop, float $lat, float $lng): array
    {
        if ($shop->lat === null || $shop->lng === null) {
            throw new DeliveryUnavailableException('shop_has_no_location', __('delivery.errors.shop_has_no_location'));
        }

        $km = Haversine::km((float) $shop->lat, (float) $shop->lng, $lat, $lng);
        $max = DeliverySettings::maxDistanceKm();
        if ($max > 0 && $km > $max) {
            throw new DeliveryUnavailableException('too_far', __('delivery.too_far', ['km' => rtrim(rtrim(number_format($max, 1), '0'), '.')]));
        }

        return [
            'estimate' => $this->bolt->quote(['lat' => (float) $shop->lat, 'lng' => (float) $shop->lng], ['lat' => $lat, 'lng' => $lng]),
            'distance_km' => round($km, 2),
        ];
    }
}

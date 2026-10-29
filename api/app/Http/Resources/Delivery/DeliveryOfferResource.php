<?php

namespace App\Http\Resources\Delivery;

use App\Models\DeliveryOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a driver sees BEFORE accepting: enough to decide (pay, distance,
 * shop, rough drop-off area), but no customer name, phone or exact address.
 * Those arrive in DeliveryJobResource only once the job is theirs.
 *
 * @mixin DeliveryOffer
 */
class DeliveryOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $job = $this->job;
        $seller = $job->order->seller;

        return [
            'job_id' => $job->id,
            'offered_at' => $this->offered_at,
            'wave' => $this->wave,
            'distance_to_shop_km' => round($this->distance_km, 1),
            'trip_km' => $job->distance_km,
            'driver_earnings' => $job->driver_earnings,
            'fee' => $job->fee,
            'collect_cash' => $job->payment_status === 'cash' ? $job->fee : 0,
            'items_count' => (int) $job->order->items->sum('qty'),
            'shop' => [
                'name' => $seller?->shop_name,
                'district' => $seller?->district,
                'lat' => $job->pickup_lat,
                'lng' => $job->pickup_lng,
            ],
            // Rounded to ~1 km so the exact home address isn't revealed before acceptance.
            'dropoff_area' => [
                'lat' => round($job->dropoff_lat, 2),
                'lng' => round($job->dropoff_lng, 2),
            ],
        ];
    }
}

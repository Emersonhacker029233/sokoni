<?php

namespace App\Support;

/**
 * Typed reads for the admin-overridable delivery values. Same mechanism as
 * App\Support\Settings (a `settings` table row wins, else config('sokoni.*')),
 * kept in its own class so the existing Settings page and Settings::all()
 * are untouched.
 *
 * The delivery fee itself is not here: it is Bolt's estimate for the trip
 * (the upper bound), frozen on the job when the shop accepts.
 */
class DeliverySettings
{
    /** Furthest drop-off from the shop Sokoni offers delivery for (straight line). */
    public static function maxDistanceKm(): float
    {
        return (float) Settings::get('delivery_max_distance_km');
    }

    /** Kept from the refund when the buyer cancels after Bolt assigned a driver. */
    public static function buyerCancelFee(): int
    {
        return (int) Settings::get('delivery_buyer_cancel_fee');
    }

    /** Minutes a delivery may wait for its fee before it is auto-cancelled. */
    public static function unpaidTimeoutMinutes(): int
    {
        return max(1, (int) Settings::get('delivery_unpaid_timeout_minutes'));
    }
}

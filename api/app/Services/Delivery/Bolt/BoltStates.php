<?php

namespace App\Services\Delivery\Bolt;

/**
 * Bolt ride states -> Sokoni delivery statuses.
 *
 * For a delivery (shop -> customer) Bolt's "client" is the SHOP: Sokoni
 * books the ride with the shop as the rider, so "arrived at client" means
 * the driver is at the shop and "driving with client" means the parcel is
 * on its way to the customer.
 *
 * | Bolt                        | delivery_jobs.status | failure_reason            |
 * |-----------------------------|----------------------|---------------------------|
 * | SEARCHING                   | pending_driver       |                           |
 * | DRIVER_ON_ROUTE_TO_CLIENT   | driver_assigned      |                           |
 * | ARRIVED_AT_CLIENT           | arrived_at_shop      |                           |
 * | DRIVING_WITH_CLIENT         | on_the_way           |                           |
 * | COMPLETED                   | delivered            |                           |
 * | NO_DRIVER_FOUND             | no_driver_found      | no_driver_found           |
 * | CLIENT_DID_NOT_SHOW         | failed               | shop_no_show              |
 * | PAYMENT_BOOKING_FAILED      | failed               | payment_booking_failed    |
 * | CANCELLED                   | failed               | provider_cancelled(*)     |
 * | CLIENT_CANCELLED            | failed               | client_cancelled(*)       |
 *
 * (*) Only when Sokoni did not cancel it itself (a job Sokoni cancelled is
 * already terminal and is never synced again). With the parcel already in
 * the car the reason gets an `_in_transit` suffix and support is alerted:
 * the goods must be recovered by a person, not by a button.
 */
final class BoltStates
{
    public const ACTIVE = ['SEARCHING', 'DRIVER_ON_ROUTE_TO_CLIENT', 'ARRIVED_AT_CLIENT', 'DRIVING_WITH_CLIENT'];

    /** Before the parcel leaves the pickup point: the ride can still be cancelled. */
    public const BEFORE_PICKUP = ['SEARCHING', 'DRIVER_ON_ROUTE_TO_CLIENT', 'ARRIVED_AT_CLIENT'];

    /**
     * @return array{status: string, failure_reason: ?string}|null null for an unknown state (kept as provider_state only)
     */
    public static function forDelivery(string $boltState, bool $parcelInCar): ?array
    {
        return match (strtoupper($boltState)) {
            'SEARCHING' => ['status' => 'pending_driver', 'failure_reason' => null],
            'DRIVER_ON_ROUTE_TO_CLIENT' => ['status' => 'driver_assigned', 'failure_reason' => null],
            'ARRIVED_AT_CLIENT' => ['status' => 'arrived_at_shop', 'failure_reason' => null],
            'DRIVING_WITH_CLIENT' => ['status' => 'on_the_way', 'failure_reason' => null],
            'COMPLETED' => ['status' => 'delivered', 'failure_reason' => null],
            'NO_DRIVER_FOUND' => ['status' => 'no_driver_found', 'failure_reason' => 'no_driver_found'],
            'CLIENT_DID_NOT_SHOW' => ['status' => 'failed', 'failure_reason' => 'shop_no_show'],
            'PAYMENT_BOOKING_FAILED' => ['status' => 'failed', 'failure_reason' => 'payment_booking_failed'],
            'CANCELLED' => ['status' => 'failed', 'failure_reason' => $parcelInCar ? 'provider_cancelled_in_transit' : 'provider_cancelled'],
            'CLIENT_CANCELLED' => ['status' => 'failed', 'failure_reason' => $parcelInCar ? 'client_cancelled_in_transit' : 'client_cancelled'],
            default => null,
        };
    }

    /**
     * Return rides (customer -> shop) use their own small vocabulary on
     * return_requests.ride_status: the pickup point is the customer.
     */
    public static function forReturnRide(string $boltState): ?string
    {
        return match (strtoupper($boltState)) {
            'SEARCHING' => 'pending_driver',
            'DRIVER_ON_ROUTE_TO_CLIENT' => 'driver_assigned',
            'ARRIVED_AT_CLIENT' => 'at_pickup',
            'DRIVING_WITH_CLIENT' => 'on_the_way',
            'COMPLETED' => 'delivered',
            'NO_DRIVER_FOUND' => 'no_driver_found',
            'CLIENT_DID_NOT_SHOW', 'PAYMENT_BOOKING_FAILED', 'CANCELLED', 'CLIENT_CANCELLED' => 'failed',
            default => null,
        };
    }

    /** Reasons the buyer can fix by booking again (the parcel is still at the shop). */
    public static function retryable(?string $failureReason): bool
    {
        return in_array($failureReason, ['no_driver_found', 'shop_no_show', 'payment_booking_failed', 'provider_cancelled', 'client_cancelled', 'booking_failed', 'quote_failed', 'no_driver_available'], true);
    }
}

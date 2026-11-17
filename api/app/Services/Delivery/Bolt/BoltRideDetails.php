<?php

namespace App\Services\Delivery\Bolt;

/**
 * GET /rides (ride details): the state, and the driver and tracking link
 * once Bolt has assigned someone. Bolt's documented states:
 * SEARCHING, DRIVER_ON_ROUTE_TO_CLIENT, ARRIVED_AT_CLIENT,
 * DRIVING_WITH_CLIENT, COMPLETED, NO_DRIVER_FOUND, CANCELLED,
 * CLIENT_CANCELLED, CLIENT_DID_NOT_SHOW, PAYMENT_BOOKING_FAILED.
 */
final class BoltRideDetails
{
    public function __construct(
        public readonly string $rideId,
        public readonly string $state,
        public readonly ?int $etaToClientSeconds = null,
        public readonly ?string $trackingUrl = null,
        public readonly ?string $driverName = null,
        public readonly ?string $driverPhone = null,
        public readonly ?string $driverPicture = null,
        public readonly ?string $plate = null,
        public readonly ?string $model = null,
    ) {}

    public static function fromArray(array $body): self
    {
        $vehicle = is_array($body['vehicle'] ?? null) ? array_change_key_case($body['vehicle'], CASE_LOWER) : [];
        $text = fn (mixed $v) => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
        $eta = $body['eta_to_client'] ?? null;

        return new self(
            (string) ($body['ride_id'] ?? ''),
            strtoupper((string) ($body['state'] ?? 'UNKNOWN')),
            is_numeric($eta) ? (int) $eta : null,
            $text($body['tracking_url'] ?? null),
            $text($vehicle['driver_name'] ?? null),
            $text($vehicle['driver_phone'] ?? null),
            $text($vehicle['driver_picture'] ?? null),
            $text($vehicle['car_reg_number'] ?? null),
            $text($vehicle['car_model'] ?? null),
        );
    }

    /**
     * What Sokoni stores in `provider_driver` and shows the buyer and the
     * shop: name, phone, plate, model, photo, tracking link and ETA.
     * Null until Bolt has a driver.
     */
    public function driver(): ?array
    {
        if ($this->driverName === null && $this->plate === null && $this->trackingUrl === null) {
            return null;
        }

        return [
            'name' => $this->driverName,
            'phone' => $this->driverPhone,
            'plate' => $this->plate,
            'model' => $this->model,
            'photo_url' => $this->driverPicture,
            'tracking_url' => $this->trackingUrl,
            'eta_seconds' => $this->etaToClientSeconds,
        ];
    }
}

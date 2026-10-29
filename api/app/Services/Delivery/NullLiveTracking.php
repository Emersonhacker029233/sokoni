<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\User;

/**
 * MOCK: no Firebase RTDB configured. The apps fall back to polling
 * GET /api/delivery/orders/{order}, which returns the driver's last
 * heartbeat position while the job is active.
 */
class NullLiveTracking implements LiveTracking
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function open(DeliveryJob $job): void {}

    public function close(DeliveryJob $job): void {}

    public function credentialsFor(User $user): ?array
    {
        return null;
    }
}

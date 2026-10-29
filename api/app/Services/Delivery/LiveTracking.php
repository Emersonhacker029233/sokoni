<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\User;

/**
 * Live driver position for one active job, visible only to that job's
 * buyer and driver. Laravel never stores the 4-second stream. It only
 * opens the channel on assignment and deletes it when the job ends.
 */
interface LiveTracking
{
    public function isEnabled(): bool;

    public function open(DeliveryJob $job): void;

    public function close(DeliveryJob $job): void;

    /** Short-lived credentials the app uses for its job's channel, or null when tracking is off. */
    public function credentialsFor(User $user): ?array;
}

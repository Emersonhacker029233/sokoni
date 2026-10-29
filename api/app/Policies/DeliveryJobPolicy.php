<?php

namespace App\Policies;

use App\Models\DeliveryJob;
use App\Models\User;

/**
 * Who may see a delivery job: its order's buyer, its order's seller
 * (read-only), its assigned driver, and admins. Nobody else, including
 * drivers who were merely offered it. They see DeliveryOfferResource only.
 */
class DeliveryJobPolicy
{
    public function view(User $user, DeliveryJob $job): bool
    {
        return $user->is_admin
            || $this->isBuyer($user, $job)
            || $this->isSeller($user, $job)
            || $this->isDriver($user, $job);
    }

    public function actAsBuyer(User $user, DeliveryJob $job): bool
    {
        return $this->isBuyer($user, $job);
    }

    public function actAsDriver(User $user, DeliveryJob $job): bool
    {
        return $this->isDriver($user, $job);
    }

    public function isBuyer(User $user, DeliveryJob $job): bool
    {
        return $job->order !== null && $job->order->buyer_id === $user->id;
    }

    public function isSeller(User $user, DeliveryJob $job): bool
    {
        $sellerId = $user->sellerProfileId();

        return $sellerId !== null && $job->order !== null && $job->order->seller_id === $sellerId;
    }

    public function isDriver(User $user, DeliveryJob $job): bool
    {
        $driverId = $user->driverProfile?->id;

        return $driverId !== null && $job->driver_profile_id === $driverId;
    }
}

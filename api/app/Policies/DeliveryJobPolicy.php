<?php

namespace App\Policies;

use App\Models\DeliveryJob;
use App\Models\User;

/** Who may see a delivery: its order's buyer and its order's seller (read-only), plus admins. */
class DeliveryJobPolicy
{
    public function view(User $user, DeliveryJob $job): bool
    {
        return $user->is_admin || $this->isBuyer($user, $job) || $this->isSeller($user, $job);
    }

    /** GET /delivery/orders/{order} is for the order's two parties only. */
    public function viewInCustomerApp(User $user, DeliveryJob $job): bool
    {
        return $this->isBuyer($user, $job) || $this->isSeller($user, $job);
    }

    public function actAsBuyer(User $user, DeliveryJob $job): bool
    {
        return $this->isBuyer($user, $job);
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
}

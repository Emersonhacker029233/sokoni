<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_id || $user->sellerProfileId() === $order->seller_id;
    }

    /** Only the seller advances pending → accepted → ready → completed. */
    public function advance(User $user, Order $order): bool
    {
        return $user->sellerProfileId() === $order->seller_id;
    }

    /** Either party may cancel while the order is still cancellable. */
    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}

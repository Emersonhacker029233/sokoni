<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Admin panel browsing (CLAUDE.md admin rebuild, Section 4) — both roles, an order is not something to hide from Staff. */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->is_admin || $user->id === $order->buyer_id || $user->sellerProfileId() === $order->seller_id;
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

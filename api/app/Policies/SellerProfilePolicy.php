<?php

namespace App\Policies;

use App\Models\SellerProfile;
use App\Models\User;

class SellerProfilePolicy
{
    public function update(User $user, SellerProfile $seller): bool
    {
        return $user->id === $seller->user_id;
    }
}

<?php

namespace App\Policies;

use App\Models\Update;
use App\Models\User;

class UpdatePolicy
{
    public function delete(User $user, Update $update): bool
    {
        return $user->sellerProfileId() === $update->seller_id;
    }
}

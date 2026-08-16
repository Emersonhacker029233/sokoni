<?php

namespace App\Policies;

use App\Models\Showcase;
use App\Models\User;

class ShowcasePolicy
{
    public function delete(User $user, Showcase $showcase): bool
    {
        return $user->sellerProfileId() === $showcase->seller_id;
    }
}

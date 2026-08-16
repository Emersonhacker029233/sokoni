<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function delete(User $user, Offer $offer): bool
    {
        return $user->sellerProfileId() === $offer->seller_id;
    }
}

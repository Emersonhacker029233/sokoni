<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /** Admin panel browsing/moderation (both roles — Section 6: "Staff... moderation and verification only"). */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->is_admin || $this->update($user, $product);
    }

    /** The API's own seller-facing edit/delete — deliberately unrelated to the admin check above. */
    public function update(User $user, Product $product): bool
    {
        return $user->sellerProfileId() === $product->seller_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}

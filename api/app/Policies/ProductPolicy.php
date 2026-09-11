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

    /**
     * D1 (tester feedback): "full CRUD for products" in the admin panel.
     * `update`/`delete` were seller-ownership-only before this — correct
     * for the seller-facing web/API endpoints that also call through this
     * same policy (ShopProductMediaController, Api\ProductController),
     * but it would have 403'd an admin's OWN EditProduct page the moment
     * one was added, since an admin has no seller_id to match. Admins get
     * both, exactly like `view` above already does.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->is_admin || $user->sellerProfileId() === $product->seller_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}

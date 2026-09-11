<?php

namespace App\Policies;

use App\Models\ProductMedia;
use App\Models\User;

/**
 * D1 (tester feedback): explicit admin-only gating for ProductMedia, now
 * that MediaRelationManager gives it a real, mutable admin-facing consumer
 * beyond the standalone, list-only ProductMediaResource (which, like most
 * models in this app, has run fine with no dedicated policy at all —
 * Laravel/Filament default-allow when no policy is registered for a
 * class). Not required to make the relation manager render — that turned
 * out to be an unrelated Livewire testing quirk (relation managers
 * lazy-load their content behind a Livewire partial by default, so a
 * plain page GET never contains their markup regardless of authorization
 * — see MediaRelationManager's own test coverage) — added anyway as
 * correct, explicit hardening now that admin write access to this model
 * actually exists, matching ProductPolicy/UserPolicy's own style.
 */
class ProductMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, ProductMedia $media): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, ProductMedia $media): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, ProductMedia $media): bool
    {
        return $user->is_admin;
    }
}

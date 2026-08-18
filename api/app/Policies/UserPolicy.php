<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management is Admin-only (CLAUDE.md admin rebuild, Section 6:
 * "Staff... no user management, no settings"). Filament's Resource
 * authorization checks these standard CRUD abilities automatically for
 * every page/action on UserResource — this is real enforcement (a direct
 * request to `/admin/users` 403s for Staff), not just a hidden nav item.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminRole();
    }

    public function view(User $user): bool
    {
        return $user->isAdminRole();
    }

    public function create(User $user): bool
    {
        return $user->isAdminRole();
    }

    public function update(User $user): bool
    {
        return $user->isAdminRole();
    }

    public function delete(User $user): bool
    {
        return $user->isAdminRole();
    }
}

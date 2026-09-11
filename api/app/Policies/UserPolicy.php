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

    /**
     * D1 (tester feedback): "never allow deleting own account or another
     * admin" — this used to ignore $model entirely (a bare
     * `isAdminRole()` check), so it authorized deleting *any* user
     * including yourself or a fellow admin; only `UsersTable`'s own
     * `isProtectedFromDeletion()` closure actually enforced this, and
     * only on that one action. Filament's generic `DeleteAction` (as
     * used, unprotected, on EditUser's header — now removed for the
     * same reason) authorizes purely through this policy, so the rule
     * belongs here too, not only in one UI's action closure.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdminRole() && $user->id !== $model->id && ! $model->is_admin;
    }
}

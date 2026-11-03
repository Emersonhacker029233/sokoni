<?php

namespace App\Policies;

use App\Models\DriverProfile;
use App\Models\User;

/**
 * Admin panel access to driver accounts and their identity documents:
 * the `admin` role only. Staff, who handle seller verification, can't see
 * drivers (client decision 2026-11-03: "only admins").
 *
 * Nothing is created, edited or deleted from the panel. Drivers manage
 * their own profile in Sokoni Driver; admins only decide on it through
 * DriverReviewService. No API route authorizes against this model, so this
 * policy affects the panel only.
 */
class DriverProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminRole();
    }

    public function view(User $user, DriverProfile $driver): bool
    {
        return $user->isAdminRole();
    }

    /** Approve / reject / suspend / reinstate / unlock PIN. */
    public function review(User $user, DriverProfile $driver): bool
    {
        return $user->isAdminRole();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DriverProfile $driver): bool
    {
        return false;
    }

    public function delete(User $user, DriverProfile $driver): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

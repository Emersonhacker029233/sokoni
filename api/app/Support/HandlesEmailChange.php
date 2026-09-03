<?php

namespace App\Support;

use App\Models\User;

/**
 * Single place a profile update's "did the email change?" logic lives —
 * both the website's settings form and the app's own profile endpoint go
 * through this, so a changed address always gets the same treatment:
 * verification is reset and the link is re-sent. An email is never
 * required (NIDA-only verification is what gates a seller, not this), so
 * clearing it back to null is a normal action, not an error.
 */
class HandlesEmailChange
{
    public static function apply(User $user, ?string $newEmail): void
    {
        $newEmail = $newEmail !== null && trim($newEmail) !== '' ? trim($newEmail) : null;

        if ($newEmail === $user->email) {
            return;
        }

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => null,
        ])->save();

        if ($newEmail !== null) {
            $user->sendEmailVerificationNotification();
        }
    }
}

<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Apple Guideline 1.2: users agree to the Terms before they sign in or
 * register. Since 1.0 (9) the app shows the Terms step first and then
 * sends `terms_version` with every sign-in request, so the agreement is
 * stored on the account in the same request that signs it in, including
 * an account the sign-in creates (phone code, Google, Apple).
 *
 * Optional on purpose: builds before 1.0 (9) never send it, and the live
 * API serves them too. Without it nothing is recorded and the app's own
 * after-sign-in check asks instead, exactly as before.
 */
final class TermsAcceptance
{
    public static function recordFrom(Request $request, User $user): void
    {
        $version = $request->input('terms_version');
        if (! is_string($version) || $version === '' || mb_strlen($version) > 20) {
            return;
        }

        $user->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => $version,
        ])->save();
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Deliberately NOT behind `auth`/`signed`-plus-session middleware the way
 * Laravel's own Breeze scaffolding gates this route — this app's users are
 * primarily on the mobile app, and the browser that opens a link from an
 * email client has no reason to already carry a logged-in website session.
 * The signed URL itself (expiring, tamper-proof) plus the `hash` argument
 * matching that exact user's current email are the real proof of intent;
 * requiring the clicking browser to *also* be an authenticated session for
 * that same user would just lock most people out of verifying at all.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('web.home')->with('status', __('site.email_verified_success'));
    }
}

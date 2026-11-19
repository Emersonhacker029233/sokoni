<?php

namespace App\Http\Controllers\Web\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sign in with Apple on Android. The app's `sign_in_with_apple` web flow
 * opens Apple's page in a Chrome Custom Tab; Apple then POSTs the result
 * (form_post) to this URL, which is the Service ID's Return URL and the
 * app's `APPLE_REDIRECT_URI` (https://sokoni.co.tz/auth/apple/callback).
 *
 * Nothing is verified or stored here. The result goes straight back to
 * the app through the plugin's `signinwithapple://callback` intent, and
 * the app then sends the identity token to `POST /api/auth/social`, where
 * `HttpSocialAuthVerifier` checks it against Apple's keys. Only the fields
 * the plugin reads are forwarded.
 *
 * CSRF-exempt (bootstrap/app.php): Apple, not a browser with a Sokoni
 * session, is the caller.
 */
class AppleSignInCallbackController
{
    /** What Apple posts back and the plugin reads. */
    private const FIELDS = ['code', 'id_token', 'state', 'user', 'error'];

    public function __invoke(Request $request): RedirectResponse
    {
        $params = array_filter(
            $request->only(self::FIELDS),
            fn ($value) => is_string($value) && $value !== '',
        );

        $package = config('services.apple.android_package');

        return redirect()->away(
            'intent://callback?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986)
                ."#Intent;package={$package};scheme=signinwithapple;end",
            307,
        );
    }
}

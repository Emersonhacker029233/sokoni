<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialAuth\SocialUserResolver;
use App\Services\SocialAuth\VerifiedIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * A real browser OAuth redirect (unlike the mobile app, which verifies an
 * ID token from Google's native SDK) — gated entirely on
 * `self::isConfigured()`, which `web.auth.login`'s view uses to hide the
 * button cleanly rather than sending a visitor into a redirect that would
 * 500 (CLAUDE.md: "hide the button cleanly when they aren't [configured]").
 */
class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(SocialUserResolver $resolver): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('web.login')->withErrors(['google' => 'Google sign-in failed. Please try again.']);
        }

        $identity = new VerifiedIdentity(
            provider: 'google',
            providerId: $googleUser->getId(),
            email: $googleUser->getEmail(),
            name: $googleUser->getName(),
            avatar: $googleUser->getAvatar(),
        );

        ['user' => $user] = $resolver->resolve($identity);

        if ($user->isBanned()) {
            return redirect()->route('web.login')->withErrors(['google' => 'This account has been suspended.']);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('web.account.dashboard'));
    }
}

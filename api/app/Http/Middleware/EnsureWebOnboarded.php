<?php

namespace App\Http\Middleware;

use App\Support\Legal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every signed-in web page: terms acceptance and the buy/sell
 * intent question must both be done before anything else, exactly the
 * order the app enforces after a fresh sign-in — matching CLAUDE.md's
 * "Terms acceptance recorded exactly as the app does... After
 * registering, ask the buy-or-sell intent question, matching the app."
 * Applied to the whole `auth:web` route group except the two onboarding
 * routes themselves (which would otherwise redirect to each other).
 */
class EnsureWebOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $request->routeIs('web.auth.terms*') || $request->routeIs('web.auth.intent*') || $request->routeIs('web.logout')) {
            return $next($request);
        }

        if ($user->terms_accepted_at === null || $user->terms_version !== Legal::TERMS_VERSION) {
            return redirect()->route('web.auth.terms');
        }

        if ($user->account_intent === null) {
            return redirect()->route('web.auth.intent');
        }

        return $next($request);
    }
}

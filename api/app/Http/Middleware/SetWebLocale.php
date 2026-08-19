<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * EN/SW switcher for the public website — a `?lang=` query param sets and
 * persists the choice in session (`/?lang=sw` from anywhere), otherwise
 * the session value carries over, otherwise the request's `Accept-Language`
 * header, otherwise English. Deliberately separate from the app's own
 * locale handling (User::locale) — a browsing visitor here has no account
 * yet most of the time.
 */
class SetWebLocale
{
    public const SUPPORTED = ['en', 'sw'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if ($requested && in_array($requested, self::SUPPORTED, true)) {
            $request->session()->put('web_locale', $requested);
        }

        $locale = $request->session()->get('web_locale')
            ?? $this->preferredFromHeader($request)
            ?? 'en';

        App::setLocale($locale);

        return $next($request);
    }

    private function preferredFromHeader(Request $request): ?string
    {
        $header = (string) $request->server('HTTP_ACCEPT_LANGUAGE', '');

        return str_starts_with($header, 'sw') ? 'sw' : null;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * EN/SW switcher for the public website — a `?lang=` query param sets
 * and persists the choice in session (`/?lang=sw` from anywhere), and
 * once set that stored choice is respected above everything else on
 * every later request, including one whose browser sends an
 * Accept-Language header that would otherwise have suggested a
 * different language. Deliberately separate from the app's own locale
 * handling (User::locale) — a browsing visitor here has no account yet
 * most of the time.
 *
 * Language audit (client feedback): "Kiswahili is the primary language
 * of this marketplace's users... default to Kiswahili... do not default
 * to English just because a browser is configured in the United
 * States." The previous version of this middleware did the opposite of
 * that: it defaulted to English and only switched to Kiswahili when the
 * header explicitly started with "sw" — meaning EVERY visitor without a
 * stored preference whose browser wasn't literally set to Swahili (i.e.
 * almost everyone, including Tanzanian visitors on an English-language
 * Android build) saw English by default. Flipped: default is now
 * Kiswahili, and English is only chosen when the header clearly
 * indicates it.
 */
class SetWebLocale
{
    public const SUPPORTED = ['sw', 'en'];

    public const DEFAULT = 'sw';

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if ($requested && in_array($requested, self::SUPPORTED, true)) {
            $request->session()->put('web_locale', $requested);
        }

        $locale = $request->session()->get('web_locale')
            ?? $this->preferredFromHeader($request)
            ?? self::DEFAULT;

        App::setLocale($locale);
        // Without this, Carbon's own ->translatedFormat() (month/day
        // names) stays English regardless of App::setLocale() — the two
        // are configured independently. See order-show.blade.php's
        // timestamps, the first thing this fix was for.
        Carbon::setLocale($locale);

        return $next($request);
    }

    /**
     * Only ever returns 'en' (a clear, explicit signal) or null — this
     * middleware's default is already Kiswahili, so there's nothing to
     * gain by trying to detect "sw" here too, and every other value
     * falls through to that Kiswahili default rather than guessing
     * English.
     *
     * "en-US" specifically is deliberately NOT treated as a clear
     * signal (client feedback: "do not default to English just because
     * a browser is configured in the United States") — it's what an
     * out-of-the-box Android device's factory region setting sends
     * regardless of whether its owner actually reads English, unlike a
     * bare "en" or a non-US English variant ("en-GB", "en-TZ", ...),
     * which reflect an actual language choice rather than a region
     * default.
     */
    private function preferredFromHeader(Request $request): ?string
    {
        $header = strtolower((string) $request->server('HTTP_ACCEPT_LANGUAGE', ''));
        $primary = explode(',', $header)[0] ?? '';

        if (! str_starts_with($primary, 'en')) {
            return null;
        }

        return str_starts_with($primary, 'en-us') ? null : 'en';
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Part 5 (client feedback): "the admin has no way to switch between
 * light and dark, and no language selector... admin interface strings
 * are English by default." That premise stopped being true the moment
 * `config('app.locale')` became `'sw'` for the language round (Part 1)
 * — with nothing in the admin panel setting its own locale, it just
 * inherited that global default, and Filament genuinely ships a real,
 * mostly-complete Swahili translation of its own chrome (confirmed by
 * reading `vendor/filament/*\/resources/lang/sw/`, not assumed), so the
 * panel had quietly started rendering as an unplanned, half-translated
 * mix (Filament's own buttons/labels in Swahili, every one of this
 * app's own resource field labels still hardcoded English, since none
 * of them are wrapped in `__()` — see DECISIONS.md) rather than either
 * language consistently.
 *
 * This restores "English by default" deliberately (an explicit choice
 * here, not an accident of some other feature's default), while making
 * an admin's own choice take effect for real, for exactly the portion
 * of the panel that already has a real translation to switch to:
 * Filament's own chrome. `admin_locale` (not the shared `locale`
 * column) is deliberately a separate preference — see that migration's
 * own docblock for why reusing `locale` would just reintroduce the same
 * accidental-Swahili-default problem this exists to fix. Translating
 * the rest (this app's own resource labels, none of which are wrapped
 * in `__()` today) is a separate, unstarted piece of work — see
 * DECISIONS.md.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->user()?->admin_locale ?? 'en');

        return $next($request);
    }
}

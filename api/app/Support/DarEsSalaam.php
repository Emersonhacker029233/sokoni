<?php

namespace App\Support;

/**
 * The website's "near you" anchor when no visitor location is known
 * (CLAUDE.md: "'Near you' row when a location is known, falling back to
 * Dar es Salaam") — server-rendered pages can't ask the browser for
 * geolocation before first paint without either blocking render or
 * shipping a client-only fallback state, both wrong for an SEO-first
 * page. Real per-visitor geolocation is a reasonable later enhancement,
 * not built this pass — see DECISIONS.md.
 */
class DarEsSalaam
{
    public const LAT = -6.7924;

    public const LNG = 39.2083;
}

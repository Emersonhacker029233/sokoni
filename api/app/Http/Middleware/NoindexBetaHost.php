<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the beta deployment out of search results without blocking the
 * crawl — a robots.txt Disallow would stop Google from ever reading a
 * noindex directive, which is the opposite of the intent. Gated on the
 * request hostname (config('sokoni.beta_hostname')) rather than an env
 * flag, so this stops applying automatically once the site is served from
 * the main domain — no manual toggle-off step at go-live.
 */
class NoindexBetaHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $betaHostname = config('sokoni.beta_hostname');

        if ($betaHostname && $request->getHost() === $betaHostname) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}

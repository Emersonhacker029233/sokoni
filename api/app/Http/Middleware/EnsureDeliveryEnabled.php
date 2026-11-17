<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BOLT_ENABLED=false: every delivery route behaves as if it does not exist
 * (404 before authentication, with the same message Laravel gives a route
 * that really isn't there, so a probe can't tell the difference).
 * `delivery.enabled:returns` additionally needs DELIVERY_RETURNS_ENABLED.
 */
class EnsureDeliveryEnabled
{
    public function handle(Request $request, Closure $next, string $feature = 'delivery'): Response
    {
        if (! config('delivery.enabled') || ($feature === 'returns' && ! config('delivery.returns_enabled'))) {
            throw self::routeNotFound($request);
        }

        return $next($request);
    }

    /** Exactly what Laravel throws for an unknown route. */
    public static function routeNotFound(Request $request): NotFoundHttpException
    {
        return new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
    }
}

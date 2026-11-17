<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BOLT_ENABLED=false: every delivery route behaves as if it does not exist
 * (404 before authentication, so a probe can't even tell it is there).
 * `delivery.enabled:returns` additionally needs DELIVERY_RETURNS_ENABLED.
 */
class EnsureDeliveryEnabled
{
    public function handle(Request $request, Closure $next, string $feature = 'delivery'): Response
    {
        abort_unless((bool) config('delivery.enabled'), 404);
        abort_if($feature === 'returns' && ! config('delivery.returns_enabled'), 404);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\DeliveryAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After auth: the signed-in user must be inside the dark launch
 * (BOLT_TEST_PHONES, never the App Store review account). Everyone else
 * gets the same 404 as when the feature is switched off.
 * `delivery.access:returns` checks returns instead of delivery.
 */
class EnsureDeliveryAccess
{
    public function handle(Request $request, Closure $next, string $feature = 'delivery'): Response
    {
        $allowed = $feature === 'returns'
            ? DeliveryAccess::returnsEnabledFor($request->user())
            : DeliveryAccess::deliveryEnabledFor($request->user());

        abort_unless($allowed, 404);

        return $next($request);
    }
}

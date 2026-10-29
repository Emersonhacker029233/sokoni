<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** DELIVERY_ENABLED=false: every delivery/driver route behaves as if it does not exist. */
class EnsureDeliveryEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('delivery.enabled'), 404);

        return $next($request);
    }
}

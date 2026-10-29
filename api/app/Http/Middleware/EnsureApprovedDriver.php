<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Work routes (go online, location, offers, jobs) need an APPROVED driver
 * profile. Onboarding routes deliberately do not use this.
 */
class EnsureApprovedDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user()?->driverProfile;

        if ($driver === null) {
            return response()->json(['code' => 'not_a_driver', 'message' => __('delivery.errors.not_a_driver')], 403);
        }
        if (! $driver->isApproved()) {
            return response()->json([
                'code' => 'driver_not_approved',
                'message' => __('delivery.errors.driver_not_approved'),
                'driver_status' => $driver->status,
            ], 403);
        }

        $request->attributes->set('driver', $driver);

        return $next($request);
    }
}

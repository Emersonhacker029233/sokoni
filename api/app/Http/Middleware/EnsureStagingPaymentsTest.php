<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The staging payment test (StagingPaymentTestController) exists only on a
 * local or staging server with STAGING_PAYMENTS_TEST_TOKEN set, for a
 * request carrying that token in X-Staging-Token. Anywhere else, production
 * included, it answers exactly like a route that doesn't exist.
 */
class EnsureStagingPaymentsTest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('payments.staging_test_token');
        $allowed = app()->environment(['local', 'staging', 'testing'])
            && strlen($token) >= 24
            && hash_equals($token, (string) $request->header('X-Staging-Token'));

        abort_unless($allowed, 404);

        return $next($request);
    }
}

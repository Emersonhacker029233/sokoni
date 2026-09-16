<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SetWebLocale::class,
            \App\Http\Middleware\NoindexBetaHost::class,
        ]);
        $middleware->alias([
            'web.onboarded' => \App\Http\Middleware\EnsureWebOnboarded::class,
        ]);
        // Laravel's Authenticate middleware redirects an unauthenticated
        // web request to a route literally named `login` by default — this
        // app's is named `web.login` (every web route is namespaced
        // `web.*`), so without this the redirect itself 500s.
        $middleware->redirectGuestsTo(fn () => route('web.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Part 3 (client feedback): "respect the existing server-side
        // rate limit... and say so clearly when the limit is reached
        // rather than failing silently." Without this, a throttled
        // web request (the `throttle:otp` middleware on
        // web.auth.otp.request) fell through to Laravel's generic 429
        // error page — a dead end with no way back to the form, and no
        // explanation a visitor could act on. The API side needs no
        // equivalent: shouldRenderJsonWhen above already gives it a
        // proper JSON 429 Laravel builds correctly on its own.
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if (! $request->routeIs('web.auth.otp.request')) {
                return null;
            }

            $retryAfterSeconds = (int) ($e->getHeaders()['Retry-After'] ?? 0);
            $minutes = $retryAfterSeconds > 0 ? (int) ceil($retryAfterSeconds / 60) : null;
            $message = $minutes
                ? "You've requested too many codes. Please try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.'
                : "You've requested too many codes. Please try again shortly.";

            return redirect()->route('web.login')->withErrors(['phone' => $message]);
        });
    })->create();

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
    })->create();

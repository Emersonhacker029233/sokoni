<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every protected API route uses the `sanctum` guard, and controllers
     * rely on `$request->user()->currentAccessToken()` (e.g. logout).
     * The base actingAs() only sets an authenticated user without a token
     * context, leaving currentAccessToken() null in tests even though a
     * real bearer-token request always has one. Route all actingAs() calls
     * through Sanctum::actingAs() so tests exercise the same shape of
     * authentication a real request does.
     */
    public function actingAs($user, $guard = null): static
    {
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    /**
     * The Filament admin panel authenticates on the `web` session guard,
     * not `sanctum` — the global actingAs() override above is specifically
     * for the API's bearer-token routes and would leave panel requests
     * unauthenticated (redirected to /admin/login) if used here instead.
     */
    public function actingAsAdmin($user): static
    {
        return parent::actingAs($user, 'web');
    }

    /**
     * The public website (sokoni.co.tz) also authenticates on the `web`
     * session guard, same mechanism as the admin panel above — a
     * clearly-named alias for that context rather than reusing
     * `actingAsAdmin()` (correct, but a confusing name) in website tests.
     */
    public function actingAsWebUser($user): static
    {
        return parent::actingAs($user, 'web');
    }
}

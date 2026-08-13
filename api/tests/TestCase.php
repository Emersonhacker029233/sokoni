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
}

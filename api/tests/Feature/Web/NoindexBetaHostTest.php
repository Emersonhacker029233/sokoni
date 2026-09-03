<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoindexBetaHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_beta_hostname_gets_a_noindex_header(): void
    {
        config(['sokoni.beta_hostname' => 'beta.sokoni.co.tz']);

        $response = $this->get('http://beta.sokoni.co.tz/');

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_production_hostname_gets_no_noindex_header(): void
    {
        config(['sokoni.beta_hostname' => 'beta.sokoni.co.tz']);

        $response = $this->get('http://sokoni.co.tz/');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function test_no_beta_hostname_configured_means_no_host_ever_gets_the_header(): void
    {
        config(['sokoni.beta_hostname' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }
}

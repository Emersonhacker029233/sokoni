<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TEMPORARY — delete alongside the route/controller it covers once the production incident is closed. */
class DiagnosticLogRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_404s_with_no_token_configured(): void
    {
        putenv('DIAGNOSTIC_LOG_TOKEN');

        $this->get('/_diagnostic/log?token=anything')->assertNotFound();
    }

    public function test_it_404s_with_the_wrong_token(): void
    {
        putenv('DIAGNOSTIC_LOG_TOKEN=real-token');

        $this->get('/_diagnostic/log?token=wrong')->assertNotFound();

        putenv('DIAGNOSTIC_LOG_TOKEN');
    }

    public function test_it_returns_the_log_tail_with_the_correct_token(): void
    {
        putenv('DIAGNOSTIC_LOG_TOKEN=real-token');
        $logPath = storage_path('logs/laravel.log');
        file_put_contents($logPath, implode("\n", array_map(fn ($i) => "line {$i}", range(1, 60))));

        $response = $this->get('/_diagnostic/log?token=real-token');

        $response->assertOk();
        $response->assertSee('line 60', false);
        $response->assertDontSee('line 1'."\n", false);

        putenv('DIAGNOSTIC_LOG_TOKEN');
    }
}

<?php

namespace Tests\Feature\Web;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Kibonet's delivery-report POST (SmsDeliveryCallbackController) has no
 * Sokoni session of its own, so it must work without a CSRF token
 * (bootstrap/app.php excludes it) and without authentication.
 */
class SmsDeliveryCallbackTest extends TestCase
{
    public function test_accepts_the_callback_without_a_csrf_token_and_logs_it(): void
    {
        Log::shouldReceive('channel')->with('sms')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'delivery report')
                && $context['payload']['messageId'] === 'abc123'
                && $context['payload']['status'] === 'DELIVERED';
        });

        $this->post('/sms/delivery-callback', [
            'messageId' => 'abc123',
            'status' => 'DELIVERED',
        ])->assertOk();
    }
}

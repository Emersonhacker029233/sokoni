<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use Tests\TestCase;

/**
 * The SmsGateway binding (AppServiceProvider) is credential-driven, not a
 * fixed class — see docs/SMS.md. This locks in the fallback so it can't
 * silently start sending real SMS in an environment that never meant to.
 */
class SmsGatewayBindingTest extends TestCase
{
    public function test_falls_back_to_the_log_gateway_when_beem_credentials_are_unset(): void
    {
        config(['services.beem.api_key' => null, 'services.beem.secret_key' => null]);

        $this->assertInstanceOf(LogSmsGateway::class, $this->app->make(SmsGateway::class));
    }

    public function test_uses_beem_once_both_credentials_are_set(): void
    {
        config(['services.beem.api_key' => 'key', 'services.beem.secret_key' => 'secret']);

        $this->assertInstanceOf(BeemSmsGateway::class, $this->app->make(SmsGateway::class));
    }
}

<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\AfricasTalkingSmsGateway;
use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\TextifySmsGateway;
use Tests\TestCase;

/**
 * The SmsGateway binding (AppServiceProvider) is driven by `SMS_DRIVER`
 * explicitly, not by sniffing which credentials happen to be set — see
 * docs/SMS.md. This locks in the fallback so it can't silently start
 * sending real SMS in an environment that never meant to.
 */
class SmsGatewayBindingTest extends TestCase
{
    public function test_falls_back_to_the_log_gateway_when_sms_driver_is_unset(): void
    {
        config(['services.sms_driver' => null]);

        $this->assertInstanceOf(LogSmsGateway::class, $this->app->make(SmsGateway::class));
    }

    public function test_falls_back_to_the_log_gateway_for_an_unrecognised_driver(): void
    {
        config(['services.sms_driver' => 'twilio']);

        $this->assertInstanceOf(LogSmsGateway::class, $this->app->make(SmsGateway::class));
    }

    public function test_uses_textify_when_selected(): void
    {
        config([
            'services.sms_driver' => 'textify',
            'services.textify.api_key' => 'txf_key',
            'services.textify.sender_name' => 'Sokoni',
            'services.textify.endpoint' => 'https://portal.textify.africa/api/v1/messages',
        ]);

        $this->assertInstanceOf(TextifySmsGateway::class, $this->app->make(SmsGateway::class));
    }

    public function test_uses_africas_talking_when_selected(): void
    {
        config([
            'services.sms_driver' => 'africastalking',
            'services.africastalking.username' => 'skyfar',
            'services.africastalking.api_key' => 'key',
            'services.africastalking.sender_id' => 'skyfar',
        ]);

        $this->assertInstanceOf(AfricasTalkingSmsGateway::class, $this->app->make(SmsGateway::class));
    }

    public function test_uses_beem_when_selected(): void
    {
        config([
            'services.sms_driver' => 'beem',
            'services.beem.api_key' => 'key',
            'services.beem.secret_key' => 'secret',
        ]);

        $this->assertInstanceOf(BeemSmsGateway::class, $this->app->make(SmsGateway::class));
    }
}

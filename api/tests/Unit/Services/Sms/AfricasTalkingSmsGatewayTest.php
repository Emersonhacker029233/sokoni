<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\AfricasTalkingSmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AfricasTalkingSmsGatewayTest extends TestCase
{
    public function test_sends_the_english_code_with_the_expected_request_shape(): void
    {
        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Message' => 'Sent to 1/1 Total Cost: TZS 32.0000',
                    'Recipients' => [
                        ['statusCode' => 101, 'number' => '+255754123456', 'status' => 'Success', 'cost' => 'TZS 32.0000', 'messageId' => 'ATXid_1'],
                    ],
                ],
            ], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'a-real-key', 'skyfar');
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.africastalking.com/version1/messaging'
                && $request->hasHeader('apiKey', 'a-real-key')
                && $request['username'] === 'skyfar'
                && $request['to'] === '+255754123456'
                && $request['from'] === 'skyfar'
                && $request['message'] === 'Your Sokoni code: 482913. Do not share it.';
        });
    }

    public function test_sends_the_swahili_message_when_locale_is_sw(): void
    {
        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'number' => '+255754123456', 'status' => 'Success']]],
            ], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'key', 'skyfar');
        $gateway->sendOtp('+255754123456', '482913', 'sw');

        Http::assertSent(fn ($request) => $request['message'] === 'Namba yako ya Sokoni: 482913. Usimpe mtu yeyote.');
    }

    public function test_targets_the_sandbox_host_when_sandbox_is_enabled(): void
    {
        Http::fake([
            'api.sandbox.africastalking.com/*' => Http::response([
                'SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'number' => '+255754123456', 'status' => 'Success']]],
            ], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'key', 'skyfar', sandbox: true);
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.africastalking.com/version1/messaging');
    }

    public function test_defensively_re_adds_a_missing_leading_plus_on_the_phone_number(): void
    {
        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'number' => '255754123456', 'status' => 'Success']]],
            ], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'key', 'skyfar');
        $gateway->sendOtp('255754123456', '482913');

        Http::assertSent(fn ($request) => $request['to'] === '+255754123456');
    }

    public function test_http_201_does_not_mean_delivered_a_non_success_recipient_status_throws(): void
    {
        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['statusCode' => 403, 'number' => '+255754123456', 'status' => 'InvalidPhoneNumber', 'messageId' => 'None'],
                    ],
                ],
            ], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'key', 'skyfar');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('InvalidPhoneNumber');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_insufficient_balance_throws_and_logs_critical_not_a_routine_error(): void
    {
        Log::shouldReceive('channel')->with('sms')->andReturnSelf();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('critical')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'INSUFFICIENT BALANCE') && $context['status'] === 'InsufficientBalance';
        });

        Http::fake([
            'api.africastalking.com/*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [
                        ['statusCode' => 405, 'number' => '+255754123456', 'status' => 'InsufficientBalance', 'messageId' => 'None'],
                    ],
                ],
            ], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'key', 'skyfar');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('insufficient balance');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_throws_on_a_non_2xx_http_response(): void
    {
        Http::fake([
            'api.africastalking.com/*' => Http::response('Unauthorized', 401),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'wrong-key', 'skyfar');

        $this->expectException(RuntimeException::class);

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_throws_when_the_response_has_no_recipients_at_all(): void
    {
        Http::fake([
            'api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => []]], 201),
        ]);

        $gateway = new AfricasTalkingSmsGateway('skyfar', 'key', 'skyfar');

        $this->expectException(RuntimeException::class);

        $gateway->sendOtp('+255754123456', '482913');
    }
}

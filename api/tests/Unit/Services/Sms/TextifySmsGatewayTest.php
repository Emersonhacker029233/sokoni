<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\TextifySmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TextifySmsGatewayTest extends TestCase
{
    private const ENDPOINT = 'https://portal.textify.africa/api/v1/messages';

    public function test_sends_the_english_code_with_the_expected_request_shape_and_local_number(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['success' => true, 'status_code' => 200], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_realkey', 'Sokoni', self::ENDPOINT);
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(function ($request) {
            return $request->url() === self::ENDPOINT
                && $request->hasHeader('Authorization', 'Bearer txf_realkey')
                && $request['sender_name'] === 'Sokoni'
                && $request['is_scheduled'] === false
                && $request['messages'][0]['receiver'] === '0754123456'
                && $request['messages'][0]['content'] === 'Your Sokoni code: 482913. Do not share it.';
        });
    }

    public function test_sends_the_swahili_message_when_locale_is_sw(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['success' => true, 'status_code' => 200], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);
        $gateway->sendOtp('+255754123456', '482913', 'sw');

        Http::assertSent(fn ($request) => $request['messages'][0]['content'] === 'Namba yako ya Sokoni: 482913. Usimpe mtu yeyote.');
    }

    public function test_converts_e164_to_local_format_at_the_boundary(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['success' => true, 'status_code' => 200], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);
        $gateway->sendOtp('+255712345678', '482913');

        Http::assertSent(fn ($request) => $request['messages'][0]['receiver'] === '0712345678');
    }

    public function test_success_false_is_treated_as_a_failure_regardless_of_http_status(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['success' => false, 'status_code' => 400, 'message' => 'invalid receiver', 'error' => 'invalid_receiver'], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid receiver');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_invalid_token_is_handled_explicitly_and_logged_critical_not_a_routine_error(): void
    {
        Log::shouldReceive('channel')->with('sms')->andReturnSelf();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('critical')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'INVALID API KEY') && $context['error'] === 'invalid_token';
        });

        Http::fake([
            self::ENDPOINT => Http::response(['success' => false, 'status_code' => 401, 'message' => 'invalid api key', 'error' => 'invalid_token'], 401),
        ]);

        $gateway = new TextifySmsGateway('txf_wrongkey', 'Sokoni', self::ENDPOINT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid_token');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_throws_when_the_response_body_has_no_success_field_at_all(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_throws_when_the_response_body_is_not_valid_json(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response('<html>Gateway Timeout</html>', 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        $gateway->sendOtp('+255754123456', '482913');
    }

    /** C6: sendMessage reuses the exact same transport/failure handling as sendOtp, just with an arbitrary body. */
    public function test_send_message_posts_the_given_body_verbatim(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['success' => true, 'status_code' => 200], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);
        $gateway->sendMessage('+255754123456', 'New Kids category is live on Sokoni!');

        Http::assertSent(fn ($request) => $request['messages'][0]['receiver'] === '0754123456'
            && $request['messages'][0]['content'] === 'New Kids category is live on Sokoni!');
    }

    public function test_send_message_throws_on_a_failed_response_the_same_way_send_otp_does(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['success' => false, 'message' => 'blocked', 'error' => 'blocked_number'], 200),
        ]);

        $gateway = new TextifySmsGateway('txf_key', 'Sokoni', self::ENDPOINT);

        $this->expectException(RuntimeException::class);
        $gateway->sendMessage('+255754123456', 'Hello');
    }
}

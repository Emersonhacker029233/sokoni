<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\KibonetSmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class KibonetSmsGatewayTest extends TestCase
{
    private const ENDPOINT = 'https://sms.kibonet.co.tz/api/v1/vendor/message/send';

    public function test_sends_the_english_code_with_the_expected_request_shape_and_headers(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(function ($request) {
            return $request->url() === self::ENDPOINT
                && $request->hasHeader('api_key', 'key')
                && $request->hasHeader('api_secret', 'secret')
                && $request['senderId'] === 'SOKONI'
                && $request['messageType'] === 'text'
                && $request['contacts'] === '255754123456'
                && $request['message'] === 'Your Sokoni code: 482913. Do not share it.'
                && ! array_key_exists('deliveryReportUrl', $request->data());
        });
    }

    public function test_sends_the_swahili_message_when_locale_is_sw(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');
        $gateway->sendOtp('+255754123456', '482913', 'sw');

        Http::assertSent(fn ($request) => $request['message'] === 'Namba yako ya Sokoni: 482913. Usimpe mtu yeyote.');
    }

    public function test_converts_e164_to_the_configured_255_format_at_the_boundary(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');
        $gateway->sendOtp('+255712345678', '482913');

        Http::assertSent(fn ($request) => $request['contacts'] === '255712345678');
    }

    public function test_number_format_is_configurable_without_a_redeploy(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '0');
        $gateway->sendOtp('+255712345678', '482913');

        Http::assertSent(fn ($request) => $request['contacts'] === '0712345678');
    }

    public function test_includes_the_delivery_report_url_when_configured(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255', 'https://sokoni.co.tz/sms/delivery-callback');
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(fn ($request) => $request['deliveryReportUrl'] === 'https://sokoni.co.tz/sms/delivery-callback');
    }

    public function test_omits_the_delivery_report_url_entirely_when_unset_rather_than_sending_a_dead_url(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255', null);
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(fn ($request) => ! array_key_exists('deliveryReportUrl', $request->data()));
    }

    public function test_a_non_2xx_response_is_treated_as_an_auth_failure_and_throws(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['message' => 'invalid api_key or api_secret'], 401)]);

        $gateway = new KibonetSmsGateway('wrong-key', 'wrong-secret', 'SOKONI', self::ENDPOINT, '255');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid api_key or api_secret');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_an_explicit_success_false_is_treated_as_a_failure_even_on_http_200(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => false, 'message' => 'insufficient balance'], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('insufficient balance');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_an_explicit_error_status_is_treated_as_a_failure_even_on_http_200(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['status' => 'failed', 'message' => 'invalid sender id'], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid sender id');

        $gateway->sendOtp('+255754123456', '482913');
    }

    /**
     * Kibonet's own docs show no response body at all — a 2xx with an
     * empty or field-less body is the expected shape, not a malformed one
     * (unlike Textify, which requires an explicit `success` field).
     */
    public function test_a_2xx_response_with_no_recognisable_success_or_status_field_is_treated_as_success(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');

        $gateway->sendOtp('+255754123456', '482913');

        $this->assertTrue(true);
    }

    public function test_send_message_posts_the_given_body_verbatim(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $gateway = new KibonetSmsGateway('key', 'secret', 'SOKONI', self::ENDPOINT, '255');
        $gateway->sendMessage('+255754123456', 'New Kids category is live on Sokoni!');

        Http::assertSent(fn ($request) => $request['contacts'] === '255754123456'
            && $request['message'] === 'New Kids category is live on Sokoni!');
    }
}

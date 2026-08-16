<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\BeemSmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BeemSmsGatewayTest extends TestCase
{
    public function test_sends_the_code_to_beem_with_the_expected_shape(): void
    {
        Http::fake([
            'apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 1]),
        ]);

        $gateway = new BeemSmsGateway('key', 'secret', 'SOKONI');
        $gateway->sendOtp('+255754123456', '482913');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $request['source_addr'] === 'SOKONI'
                && $request['recipients'][0]['dest_addr'] === '255754123456'
                && str_contains($request['message'], '482913');
        });
    }

    public function test_throws_when_beem_reports_the_send_as_unsuccessful(): void
    {
        Http::fake([
            'apisms.beem.africa/*' => Http::response(['successful' => false, 'message' => 'Insufficient balance']),
        ]);

        $gateway = new BeemSmsGateway('key', 'secret', 'SOKONI');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $gateway->sendOtp('+255754123456', '482913');
    }

    public function test_throws_on_an_http_failure_response(): void
    {
        Http::fake([
            'apisms.beem.africa/*' => Http::response('Unauthorized', 401),
        ]);

        $gateway = new BeemSmsGateway('key', 'secret', 'SOKONI');

        $this->expectException(RuntimeException::class);

        $gateway->sendOtp('+255754123456', '482913');
    }
}

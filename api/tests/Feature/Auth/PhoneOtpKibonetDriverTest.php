<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Confirms the real `/api/auth/otp/request` route actually reaches Kibonet
 * when it's the active driver (the SmsGateway binding is resolved fresh per
 * request, not cached from whatever driver other tests leave configured),
 * and that the phone-keyed rate limiter (AppServiceProvider's `otp`
 * RateLimiter) is enforced above the gateway layer identically regardless
 * of which driver is active — see docs/SMS.md.
 */
class PhoneOtpKibonetDriverTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://sms.kibonet.co.tz/api/v1/vendor/message/send';

    private const PHONE = '+255754123456';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms_driver' => 'kibonet',
            'services.kibonet.api_key' => 'test-key',
            'services.kibonet.api_secret' => 'test-secret',
            'services.kibonet.sender_id' => 'SOKONI',
            'services.kibonet.endpoint' => self::ENDPOINT,
            'services.kibonet.number_format' => '255',
            'services.kibonet.delivery_report_url' => null,
        ]);
    }

    public function test_requesting_an_otp_sends_it_through_kibonet_with_the_expected_shape(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === self::ENDPOINT
            && $request->hasHeader('api_key', 'test-key')
            && $request->hasHeader('api_secret', 'test-secret')
            && $request['contacts'] === '255754123456');
    }

    public function test_a_fourth_otp_request_for_the_same_number_within_15_minutes_is_still_rate_limited_on_kibonet(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true], 200)]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertStatus(429);

        // The 429 is enforced before the gateway is ever called a 4th time.
        Http::assertSentCount(3);
    }

    public function test_an_auth_failure_from_kibonet_surfaces_as_a_server_error_rather_than_a_silent_ok(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['message' => 'invalid api_key or api_secret'], 401)]);

        $response = $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertStatus(500);
    }
}

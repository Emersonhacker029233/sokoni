<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Apple App Review rejection, PART B: the reviewer has no Tanzanian phone
 * number and can't receive a real OTP. `REVIEW_ACCOUNT_PHONE` +
 * `REVIEW_ACCOUNT_CODE` (both required — see `PhoneOtpService::isReviewAccount()`)
 * let one designated demo account sign in with a fixed code instead of a
 * real SMS. Every other number is completely unaffected, and the bypass
 * doesn't exist at all unless both env values are set.
 */
class ReviewAccountOtpBypassTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW_PHONE = '+255700000001';

    private const REVIEW_CODE = '135790';

    private const ORDINARY_PHONE = '+255754123456';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.review_account.phone' => self::REVIEW_PHONE,
            'services.review_account.code' => self::REVIEW_CODE,
        ]);
    }

    public function test_requesting_an_otp_for_the_review_number_sends_no_sms_and_stores_no_cached_code(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        $this->assertNull(Cache::get('otp:'.self::REVIEW_PHONE));
    }

    public function test_the_fixed_code_signs_the_review_account_in(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        $this->postJson('/api/auth/otp/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => self::REVIEW_CODE,
            'name' => 'App Review',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['phone' => self::REVIEW_PHONE]);
    }

    public function test_the_fixed_code_can_be_reused_unlike_a_real_one_time_code(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE]);
        $this->postJson('/api/auth/otp/verify', ['phone' => self::REVIEW_PHONE, 'code' => self::REVIEW_CODE, 'name' => 'A'])
            ->assertOk();

        // A second sign-in with the exact same fixed code — real codes are
        // one-time (PhoneOtpTest::test_code_cannot_be_reused_after_verification);
        // the review bypass deliberately isn't, since the reviewer needs to
        // sign in repeatedly across a review session.
        $this->postJson('/api/auth/otp/verify', ['phone' => self::REVIEW_PHONE, 'code' => self::REVIEW_CODE])
            ->assertOk();
    }

    public function test_the_wrong_code_for_the_review_number_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE]);

        $this->postJson('/api/auth/otp/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => '000000',
            'name' => 'App Review',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['phone' => self::REVIEW_PHONE]);
    }

    public function test_the_fixed_code_does_not_work_for_any_other_number(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::ORDINARY_PHONE])->assertOk();

        $this->postJson('/api/auth/otp/verify', [
            'phone' => self::ORDINARY_PHONE,
            'code' => self::REVIEW_CODE,
            'name' => 'Someone Else',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['phone' => self::ORDINARY_PHONE]);
    }

    public function test_an_ordinary_number_still_gets_a_real_random_code_sent_normally(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::ORDINARY_PHONE])->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::ORDINARY_PHONE));
    }

    public function test_every_use_of_the_bypass_is_logged_to_the_sms_channel(): void
    {
        Log::shouldReceive('channel')->with('sms')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->twice();

        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();
        $this->postJson('/api/auth/otp/verify', ['phone' => self::REVIEW_PHONE, 'code' => self::REVIEW_CODE, 'name' => 'A'])
            ->assertOk();
    }

    public function test_the_bypass_does_not_exist_when_only_the_phone_is_configured(): void
    {
        config(['services.review_account.code' => null]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        // No bypass — falls through to the real flow, so a real code is cached.
        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::REVIEW_PHONE));
    }

    public function test_the_bypass_does_not_exist_when_only_the_code_is_configured(): void
    {
        config(['services.review_account.phone' => null]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::REVIEW_PHONE));
    }

    public function test_the_bypass_does_not_exist_when_both_are_unset(): void
    {
        config(['services.review_account.phone' => null, 'services.review_account.code' => null]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::REVIEW_PHONE));
    }

    /**
     * The route-level `otp`/`otp-verify` RateLimiters (AppServiceProvider)
     * apply above PhoneOtpService — unaffected by the bypass, confirming
     * "rate-limit it as normal" rather than a parallel, unguarded path.
     */
    public function test_the_review_number_is_rate_limited_exactly_like_any_other_number(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();
        }

        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertStatus(429);
    }
}

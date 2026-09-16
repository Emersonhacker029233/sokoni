<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PhoneOtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+255754123456';

    public function test_requesting_otp_stores_a_six_digit_code(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::PHONE));
    }

    public function test_verifying_correct_code_creates_a_new_user_and_issues_a_token(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Amina Buyer',
        ])->assertOk();

        $response->assertJsonPath('user.name', 'Amina Buyer');
        $this->assertDatabaseHas('users', ['phone' => self::PHONE, 'name' => 'Amina Buyer']);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_verifying_wrong_code_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);

        $this->postJson('/api/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '000000',
            'name' => 'Amina Buyer',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    public function test_code_cannot_be_reused_after_verification(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina'])
            ->assertOk();

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina'])
            ->assertStatus(422);
    }

    public function test_returning_user_does_not_need_to_supply_name(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code])
            ->assertOk();
    }

    public function test_banned_user_cannot_sign_in(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'banned_at' => now()]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code])
            ->assertStatus(422);
    }

    public function test_an_explicit_locale_is_forwarded_to_the_sms_gateway(): void
    {
        Log::spy();

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE, 'locale' => 'sw'])->assertOk();

        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, '(sw)'))->once();
    }

    public function test_locale_defaults_to_english_when_not_supplied(): void
    {
        Log::spy();

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();

        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, '(en)'))->once();
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE, 'locale' => 'fr'])
            ->assertStatus(422);
    }

    public function test_invalid_phone_format_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => '0754123456'])
            ->assertStatus(422);
    }

    /**
     * Protects the client's SMS credit as much as it protects the phone
     * from being bombed with codes — the rate limiter is keyed on the
     * phone number (not the requester), 3 per 15 minutes, per
     * AppServiceProvider's `otp` RateLimiter definition.
     */
    public function test_a_fourth_otp_request_for_the_same_number_within_15_minutes_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();
        }

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertStatus(429);
    }

    public function test_the_rate_limit_is_scoped_per_phone_number_not_global(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();
        }
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertStatus(429);

        // A different number is entirely unaffected by the first number's limit.
        $this->postJson('/api/auth/otp/request', ['phone' => '+255755987654'])->assertOk();
    }

    public function test_otp_request_flags_a_never_seen_number_as_a_new_account(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('is_new_account', true);
    }

    public function test_otp_request_does_not_flag_an_existing_number_as_a_new_account(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('is_new_account', false);
    }

    /**
     * Part 3 (client feedback): "show when the current code expires, so
     * the user understands why it stopped working" — a real server
     * timestamp matching PhoneOtpService's own actual TTL, not a
     * duration the client would have to guess and could drift from it.
     */
    public function test_otp_request_reports_the_codes_real_expiry_time(): void
    {
        $response = $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();

        $expiresAt = \Illuminate\Support\Carbon::parse($response->json('expires_at'));
        $this->assertEqualsWithDelta(
            now()->addSeconds(\App\Services\Otp\PhoneOtpService::TTL_SECONDS)->timestamp,
            $expiresAt->timestamp,
            2,
            'expires_at should match PhoneOtpService\'s own TTL, within a couple of seconds of test execution time.'
        );
    }

    public function test_verifying_a_brand_new_number_reports_is_new_account_true(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina'])
            ->assertOk()
            ->assertJsonPath('is_new_account', true);
    }

    public function test_verifying_an_existing_number_reports_is_new_account_false(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('is_new_account', false);
    }

    public function test_signed_in_user_can_record_their_account_intent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/intent', ['intent' => 'sell'])
            ->assertOk()
            ->assertJsonPath('data.account_intent', 'sell');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_intent' => 'sell']);
    }

    public function test_account_intent_rejects_a_value_outside_the_fixed_set(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/intent', ['intent' => 'browse'])
            ->assertStatus(422);
    }

    public function test_account_intent_requires_authentication(): void
    {
        $this->postJson('/api/auth/intent', ['intent' => 'buy'])
            ->assertStatus(401);
    }
}

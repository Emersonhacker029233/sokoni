<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_invalid_phone_format_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => '0754123456'])
            ->assertStatus(422);
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

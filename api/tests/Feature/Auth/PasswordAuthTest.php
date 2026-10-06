<?php

namespace Tests\Feature\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Username/password rework (CLAUDE.md Part D). Covers the new primary
 * sign-in path (PasswordAuthController) end to end — the old phone+code
 * path (AuthController::requestOtp/verifyOtp) has its own existing
 * PhoneOtpTest, untouched by this round, and is exercised again here only
 * to confirm it still works unchanged for an account with no password.
 */
class PasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+255754123456';

    // region username availability

    public function test_an_available_username_is_reported_available(): void
    {
        $this->postJson('/api/auth/username/check', ['username' => 'amina_buyer'])
            ->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_a_taken_username_is_reported_unavailable(): void
    {
        User::factory()->create(['username' => 'amina_buyer']);

        $this->postJson('/api/auth/username/check', ['username' => 'amina_buyer'])
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_a_reserved_username_is_reported_unavailable_even_though_no_one_has_it(): void
    {
        $this->postJson('/api/auth/username/check', ['username' => 'admin'])
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_an_invalid_format_is_a_validation_error_not_an_unavailable_result(): void
    {
        $this->postJson('/api/auth/username/check', ['username' => 'a'])
            ->assertStatus(422);
    }

    // endregion

    // region login — password step

    public function test_correct_password_from_an_unrecognised_device_requires_a_code_not_an_immediate_token(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);

        $response = $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'correct-password'])
            ->assertOk();

        $response->assertJson(['requires_code' => true]);
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::PHONE));
    }

    public function test_signing_in_by_phone_number_instead_of_username_also_works(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);

        $this->postJson('/api/auth/login', ['login' => self::PHONE, 'password' => 'correct-password'])
            ->assertOk()
            ->assertJson(['requires_code' => true]);
    }

    public function test_a_recognised_device_with_no_two_factor_signs_in_immediately_no_code(): void
    {
        $user = User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);
        $deviceToken = TrustedDevice::issueFor($user);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'amina',
            'password' => 'correct-password',
            'device_token' => $deviceToken,
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $response->assertJsonMissingPath('requires_code');
    }

    public function test_two_factor_enabled_still_requires_a_code_even_from_a_recognised_device(): void
    {
        $user = User::factory()->create([
            'username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password', 'two_factor_enabled' => true,
        ]);
        $deviceToken = TrustedDevice::issueFor($user);

        $this->postJson('/api/auth/login', [
            'login' => 'amina',
            'password' => 'correct-password',
            'device_token' => $deviceToken,
        ])->assertOk()->assertJson(['requires_code' => true]);
    }

    public function test_a_device_token_belonging_to_a_different_user_is_not_trusted(): void
    {
        $owner = User::factory()->create(['phone' => '+255700000099']);
        $deviceToken = TrustedDevice::issueFor($owner);

        $other = User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);

        $this->postJson('/api/auth/login', [
            'login' => 'amina',
            'password' => 'correct-password',
            'device_token' => $deviceToken,
        ])->assertOk()->assertJson(['requires_code' => true]);
    }

    public function test_wrong_password_is_a_generic_failure(): void
    {
        User::factory()->create(['username' => 'amina', 'password' => 'correct-password']);

        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Invalid username/phone or password.');
    }

    /** CLAUDE.md 2.8: the whole point — identical response/status for "no such account" and "wrong password", so neither can be told apart. */
    public function test_a_nonexistent_username_gets_the_exact_same_error_as_a_wrong_password(): void
    {
        User::factory()->create(['username' => 'amina', 'password' => 'correct-password']);

        $realAccountWrongPassword = $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'wrong']);
        $noSuchAccount = $this->postJson('/api/auth/login', ['login' => 'nobody_here', 'password' => 'wrong']);

        $realAccountWrongPassword->assertStatus(422);
        $noSuchAccount->assertStatus(422);
        $this->assertSame($realAccountWrongPassword->json('errors'), $noSuchAccount->json('errors'));
    }

    /** An account that's never set a password (the pre-rework default) must not get a distinct message either — that would itself leak "this username exists". */
    public function test_an_unmigrated_account_with_no_password_gets_the_same_generic_error(): void
    {
        User::factory()->create(['username' => 'amina', 'password' => null]);

        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'anything'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Invalid username/phone or password.');
    }

    public function test_a_banned_account_is_rejected_after_a_correct_password(): void
    {
        User::factory()->create(['username' => 'amina', 'password' => 'correct-password', 'banned_at' => now()]);

        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'correct-password'])
            ->assertStatus(422);
    }

    /** CLAUDE.md 2.8: "admin accounts cannot be migrated or reset through public flows" — not even with the real password. */
    public function test_an_admin_account_cannot_sign_in_through_this_endpoint_even_with_the_correct_password(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'theadmin']);
        $admin->update(['password' => 'correct-password']);

        $this->postJson('/api/auth/login', ['login' => 'theadmin', 'password' => 'correct-password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Invalid username/phone or password.');
    }

    // endregion

    // region login — code step

    public function test_the_correct_code_completes_sign_in_and_issues_a_device_token(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);
        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'correct-password']);
        $code = Cache::get('otp:'.self::PHONE);

        $response = $this->postJson('/api/auth/login/verify', ['login' => 'amina', 'code' => $code])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEmpty($response->json('device_token'));
        $this->assertDatabaseCount('trusted_devices', 1);
    }

    public function test_the_device_token_from_verify_is_accepted_on_a_later_login(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);
        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'correct-password']);
        $code = Cache::get('otp:'.self::PHONE);
        $deviceToken = $this->postJson('/api/auth/login/verify', ['login' => 'amina', 'code' => $code])
            ->json('device_token');

        $this->postJson('/api/auth/login', [
            'login' => 'amina',
            'password' => 'correct-password',
            'device_token' => $deviceToken,
        ])->assertOk()->assertJsonMissingPath('requires_code');
    }

    public function test_a_wrong_code_is_a_generic_failure_and_issues_nothing(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);
        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'correct-password']);

        $this->postJson('/api/auth/login/verify', ['login' => 'amina', 'code' => '000000'])
            ->assertStatus(422);
        $this->assertDatabaseCount('trusted_devices', 0);
    }

    public function test_verifying_without_ever_requesting_a_code_fails(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'correct-password']);

        $this->postJson('/api/auth/login/verify', ['login' => 'amina', 'code' => '123456'])
            ->assertStatus(422);
    }

    // endregion

    // region setting credentials (migration + first-time)

    public function test_a_signed_in_user_can_set_their_username_and_password_for_the_first_time(): void
    {
        $user = User::factory()->create(['password' => null]);

        $response = $this->actingAs($user)->postJson('/api/auth/credentials', [
            'username' => 'amina',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertFalse($response->json('data.needs_credential_setup'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_needs_credential_setup_is_true_until_a_password_is_set(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertJsonPath('data.needs_credential_setup', true);
    }

    public function test_setting_credentials_rejects_a_taken_username(): void
    {
        User::factory()->create(['username' => 'amina']);
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->postJson('/api/auth/credentials', [
            'username' => 'amina',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_setting_credentials_rejects_a_reserved_username(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->postJson('/api/auth/credentials', [
            'username' => 'support',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    /** A stolen bearer token alone must not be enough to silently swap an already-set password — no SMS code, no proof of the old one. */
    public function test_setting_credentials_is_rejected_once_a_password_already_exists(): void
    {
        $user = User::factory()->create(['username' => 'amina', 'password' => 'original-password']);

        $this->actingAs($user)->postJson('/api/auth/credentials', [
            'username' => 'amina2',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertStatus(422);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('original-password', $user->fresh()->password));
    }

    public function test_setting_credentials_requires_a_confirmed_password_of_at_least_8_characters(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->postJson('/api/auth/credentials', [
            'username' => 'amina',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);

        $this->actingAs($user)->postJson('/api/auth/credentials', [
            'username' => 'amina',
            'password' => 'longenough',
            'password_confirmation' => 'different',
        ])->assertStatus(422);
    }

    // endregion

    // region two-factor toggle

    public function test_two_factor_can_be_turned_on_and_off_and_defaults_to_off(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->two_factor_enabled);

        $this->actingAs($user)->patchJson('/api/auth/two-factor', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        $this->actingAs($user)->patchJson('/api/auth/two-factor', ['enabled' => false])
            ->assertJsonPath('data.two_factor_enabled', false);
    }

    // endregion

    // region forgot password

    public function test_forgot_password_request_responds_identically_whether_or_not_the_account_exists(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE]);

        $real = $this->postJson('/api/auth/forgot-password/request', ['login' => 'amina']);
        $fake = $this->postJson('/api/auth/forgot-password/request', ['login' => 'nobody_here']);

        $real->assertOk();
        $fake->assertOk();
        $this->assertSame($real->json(), $fake->json());
    }

    public function test_forgot_password_request_only_actually_sends_a_code_for_a_real_account(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE]);

        $this->postJson('/api/auth/forgot-password/request', ['login' => 'amina']);
        $this->postJson('/api/auth/forgot-password/request', ['login' => 'nobody_here']);

        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:'.self::PHONE));
    }

    public function test_forgot_password_reset_sets_the_new_password_and_revokes_every_other_session(): void
    {
        $user = User::factory()->create(['username' => 'amina', 'phone' => self::PHONE, 'password' => 'old-password']);
        $oldToken = $user->createToken('old-device')->plainTextToken;

        $this->postJson('/api/auth/forgot-password/request', ['login' => 'amina']);
        $code = Cache::get('otp:'.self::PHONE);

        $response = $this->postJson('/api/auth/forgot-password/reset', [
            'login' => 'amina',
            'code' => $code,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken(explode('|', $oldToken, 2)[1]));
    }

    public function test_forgot_password_reset_fails_on_a_wrong_code(): void
    {
        User::factory()->create(['username' => 'amina', 'phone' => self::PHONE]);
        $this->postJson('/api/auth/forgot-password/request', ['login' => 'amina']);

        $this->postJson('/api/auth/forgot-password/reset', [
            'login' => 'amina',
            'code' => '000000',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);
    }

    public function test_an_admin_cannot_reset_a_password_through_this_endpoint(): void
    {
        User::factory()->admin()->create(['username' => 'theadmin', 'phone' => self::PHONE]);

        $this->postJson('/api/auth/forgot-password/request', ['login' => 'theadmin']);

        $this->assertNull(Cache::get('otp:'.self::PHONE), 'no code should ever be sent for an admin account through this public flow');
    }

    // endregion

    // region rate limiting (CLAUDE.md 2.8)

    public function test_a_sixth_login_attempt_for_the_same_login_within_15_minutes_is_rate_limited(): void
    {
        User::factory()->create(['username' => 'amina', 'password' => 'correct-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'wrong'])->assertStatus(429);
    }

    // endregion

    // region old phone+code path stays available untouched

    public function test_the_old_phone_and_code_sign_in_still_works_unchanged_for_an_account_with_no_password(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])->assertOk();
        $code = Cache::get('otp:'.self::PHONE);

        $response = $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina'])
            ->assertOk();

        $this->assertTrue($response->json('user.needs_credential_setup'));
    }

    // endregion
}

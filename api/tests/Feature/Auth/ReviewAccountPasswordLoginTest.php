<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Apple App Review rejection, Guideline 2.1 (build 1.0 (7)): the reviewer
 * typed the App Store Connect demo credentials into the first screen —
 * username/password — and got "Invalid username/phone or password.",
 * because only the OTP route knew about the review account. With
 * `REVIEW_ACCOUNT_PHONE` + `REVIEW_ACCOUNT_PASSWORD` set, that exact
 * phone + password must sign in with no second step of any kind, while
 * every other account behaves exactly as PasswordAuthTest describes.
 */
class ReviewAccountPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW_PHONE = '+255783029233';

    private const REVIEW_PASSWORD = 'Review2026';

    private const REVIEW_CODE = '123456';

    private const ORDINARY_PHONE = '+255754123456';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.review_account.phone' => self::REVIEW_PHONE,
            'services.review_account.code' => self::REVIEW_CODE,
            'services.review_account.password' => self::REVIEW_PASSWORD,
        ]);
    }

    private function reviewUser(array $attributes = []): User
    {
        return User::factory()->create([
            'phone' => self::REVIEW_PHONE,
            'username' => 'appreview',
            'password' => self::REVIEW_PASSWORD,
            ...$attributes,
        ]);
    }

    private function assertSignedInWithNoChallenge($response): void
    {
        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id']]);
        $this->assertArrayNotHasKey('requires_code', $response->json());
        $this->assertNull(Cache::get('otp:'.self::REVIEW_PHONE));
    }

    public function test_review_phone_and_password_return_a_token_with_no_two_factor_challenge(): void
    {
        $this->reviewUser();

        $response = $this->postJson('/api/auth/login', ['login' => self::REVIEW_PHONE, 'password' => self::REVIEW_PASSWORD]);

        $this->assertSignedInWithNoChallenge($response);
        $response->assertJsonPath('user.needs_credential_setup', false);
    }

    public function test_no_challenge_even_with_two_factor_enabled_and_no_recognised_device(): void
    {
        $this->reviewUser(['two_factor_enabled' => true]);

        $this->assertSignedInWithNoChallenge(
            $this->postJson('/api/auth/login', ['login' => self::REVIEW_PHONE, 'password' => self::REVIEW_PASSWORD, 'device_token' => 'not-a-real-device'])
        );
    }

    public function test_the_review_username_also_signs_in_directly(): void
    {
        $this->reviewUser();

        $this->assertSignedInWithNoChallenge(
            $this->postJson('/api/auth/login', ['login' => 'AppReview', 'password' => self::REVIEW_PASSWORD])
        );
    }

    public function test_common_ways_of_typing_the_review_phone_all_sign_in(): void
    {
        $this->reviewUser();

        foreach (['255783029233', '0783029233', '+255 783 029 233', '783029233'] as $login) {
            $this->assertSignedInWithNoChallenge(
                $this->postJson('/api/auth/login', ['login' => $login, 'password' => self::REVIEW_PASSWORD])
            );
        }
    }

    public function test_the_configured_password_works_even_if_no_password_was_ever_stored(): void
    {
        $this->reviewUser(['username' => null, 'password' => null]);

        $response = $this->postJson('/api/auth/login', ['login' => self::REVIEW_PHONE, 'password' => self::REVIEW_PASSWORD]);

        $this->assertSignedInWithNoChallenge($response);
        $response->assertJsonPath('user.needs_credential_setup', false);
    }

    public function test_a_wrong_password_for_the_review_account_still_fails_generically(): void
    {
        $this->reviewUser();

        $response = $this->postJson('/api/auth/login', ['login' => self::REVIEW_PHONE, 'password' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Invalid username/phone or password.');

        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_a_banned_review_account_cannot_sign_in(): void
    {
        $this->reviewUser(['banned_at' => now()]);

        $this->postJson('/api/auth/login', ['login' => self::REVIEW_PHONE, 'password' => self::REVIEW_PASSWORD])
            ->assertStatus(422)
            ->assertJsonMissingPath('token');
    }

    public function test_other_users_still_get_the_normal_new_device_code_step(): void
    {
        $this->reviewUser();
        User::factory()->create(['username' => 'amina', 'phone' => self::ORDINARY_PHONE, 'password' => 'correct-password']);

        $this->postJson('/api/auth/login', ['login' => 'amina', 'password' => 'correct-password'])
            ->assertOk()
            ->assertJson(['requires_code' => true])
            ->assertJsonMissingPath('token');
    }

    public function test_the_review_password_does_not_work_for_any_other_account(): void
    {
        $this->reviewUser();
        User::factory()->create(['username' => 'amina', 'phone' => self::ORDINARY_PHONE, 'password' => 'correct-password']);

        foreach (['amina', self::ORDINARY_PHONE] as $login) {
            $this->postJson('/api/auth/login', ['login' => $login, 'password' => self::REVIEW_PASSWORD])
                ->assertStatus(422)
                ->assertJsonMissingPath('token');
        }
    }

    public function test_other_users_still_see_the_credential_setup_prompt(): void
    {
        $ordinary = User::factory()->create(['phone' => self::ORDINARY_PHONE, 'password' => null]);

        $this->assertTrue($ordinary->needsCredentialSetup());
    }

    public function test_without_a_configured_password_the_review_account_is_treated_like_anyone_else(): void
    {
        config(['services.review_account.password' => null]);
        $this->reviewUser();

        $this->postJson('/api/auth/login', ['login' => self::REVIEW_PHONE, 'password' => self::REVIEW_PASSWORD])
            ->assertOk()
            ->assertJson(['requires_code' => true])
            ->assertJsonMissingPath('token');
    }

    public function test_the_otp_bypass_still_works_as_an_alternative(): void
    {
        $this->reviewUser();

        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();
        $this->postJson('/api/auth/otp/verify', ['phone' => self::REVIEW_PHONE, 'code' => self::REVIEW_CODE])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }
}

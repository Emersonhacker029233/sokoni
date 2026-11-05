<?php

namespace Tests\Feature\Moderation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Release gate for iOS 1.0 (9): the App Review account exactly as App Store
 * Connect lists it (username `appreview`, password `Review2026`, fixed code
 * `123456`), starting from the state the live account is in today (set up
 * for build 7, Terms 1.0 on file).
 *
 * The app sends `terms_version` with each sign-in after the reviewer taps
 * Agree. Every route a reviewer can take must end with a token and Terms
 * 1.1 on file, so `/auth/me` (what the app checks after sign-in) asks for
 * nothing more. Build 7, which sends no `terms_version`, must still sign in
 * exactly as before.
 */
class AppReviewCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+255783029233';

    private const USERNAME = 'appreview';

    private const PASSWORD = 'Review2026';

    private const CODE = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        // The three production .env keys (docs/DEMO.md, "App Store Connect Review Notes").
        config([
            'services.review_account.phone' => self::PHONE,
            'services.review_account.password' => self::PASSWORD,
            'services.review_account.code' => self::CODE,
        ]);
    }

    private function reviewUser(): User
    {
        return User::factory()->create([
            'phone' => self::PHONE,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'two_factor_enabled' => false,
            'terms_accepted_at' => now()->subMonth(),
            'terms_version' => '1.0',
        ]);
    }

    /** What the app reads right after sign-in to decide whether to show the Terms screen again. */
    private function assertNothingLeftToAgree(string $token): void
    {
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.terms_accepted', true)
            ->assertJsonPath('data.terms_version', '1.1')
            ->assertJsonPath('data.needs_credential_setup', false);
    }

    public function test_appreview_and_review2026_sign_in_on_the_first_screen_after_agreeing(): void
    {
        $user = $this->reviewUser();

        $response = $this->postJson('/api/auth/login', [
            'login' => self::USERNAME,
            'password' => self::PASSWORD,
            'terms_version' => '1.1',
        ])->assertOk();

        $this->assertArrayNotHasKey('requires_code', $response->json(), 'no code step for the review account');
        $response->assertJsonPath('user.terms_version', '1.1');
        $this->assertSame('1.1', $user->fresh()->terms_version);

        $this->assertNothingLeftToAgree($response->json('token'));
    }

    public function test_the_phone_typed_any_usual_way_works_the_same(): void
    {
        $this->reviewUser();

        foreach (['+255783029233', '783 029 233', '0783029233'] as $login) {
            $response = $this->postJson('/api/auth/login', [
                'login' => $login,
                'password' => self::PASSWORD,
                'terms_version' => '1.1',
            ])->assertOk();

            $this->assertArrayNotHasKey('requires_code', $response->json(), "no code step for {$login}");
            $this->assertNothingLeftToAgree($response->json('token'));
        }
    }

    public function test_123456_signs_in_on_sign_in_with_phone_number_instead(): void
    {
        $user = $this->reviewUser();

        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('is_new_account', false);
        $this->assertNull(Cache::get('otp:'.self::PHONE), 'no real code is generated or texted');

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'terms_version' => '1.1',
        ])->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
        $this->assertNothingLeftToAgree($response->json('token'));
    }

    /**
     * If REVIEW_ACCOUNT_PASSWORD were missing from the production .env, the
     * right password leads to a code step like anyone else's. 123456 still
     * finishes it, so the reviewer is never stuck.
     */
    public function test_123456_also_finishes_a_code_step_after_the_password(): void
    {
        config(['services.review_account.password' => null]);
        $this->reviewUser();

        $this->postJson('/api/auth/login', [
            'login' => self::USERNAME,
            'password' => self::PASSWORD,
            'terms_version' => '1.1',
        ])->assertOk()->assertJsonPath('requires_code', true);

        $response = $this->postJson('/api/auth/login/verify', [
            'login' => self::USERNAME,
            'code' => self::CODE,
            'terms_version' => '1.1',
        ])->assertOk();

        $this->assertNothingLeftToAgree($response->json('token'));
    }

    /** Build 7 sends no terms_version and only checks `terms_accepted`, which stays true. */
    public function test_build_7_still_signs_in_unchanged(): void
    {
        $user = $this->reviewUser();

        $response = $this->postJson('/api/auth/login', [
            'login' => self::PHONE,
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->assertArrayNotHasKey('requires_code', $response->json());
        $response->assertJsonPath('user.terms_accepted', true);
        $this->assertSame('1.0', $user->fresh()->terms_version);
    }

    public function test_a_wrong_code_is_still_refused(): void
    {
        $this->reviewUser();

        $this->postJson('/api/auth/otp/verify', ['phone' => self::PHONE, 'code' => '654321', 'terms_version' => '1.1'])
            ->assertStatus(422);
    }
}

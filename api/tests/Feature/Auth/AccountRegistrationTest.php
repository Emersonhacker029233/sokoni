<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The "Create an account" flow's own endpoints (CLAUDE.md restructure,
 * 2026-08-25) — check-only phone/handle validation for its details step,
 * and the atomic register() that verifies the OTP and creates the account
 * (and, for a seller, the SellerProfile) in one request. `/auth/otp/verify`
 * itself (the separate "Sign in" flow) is covered by PhoneOtpTest.
 */
class AccountRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+255754123456';

    public function test_check_phone_reports_false_for_a_never_seen_number(): void
    {
        $this->postJson('/api/auth/check-phone', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    public function test_check_phone_reports_true_for_an_existing_number(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/auth/check-phone', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('exists', true);
    }

    public function test_check_phone_does_not_send_an_otp(): void
    {
        $this->postJson('/api/auth/check-phone', ['phone' => self::PHONE])->assertOk();

        $this->assertNull(Cache::get('otp:'.self::PHONE), 'check-phone must be a pure read — no OTP side effect');
    }

    public function test_check_phone_rejects_a_malformed_number(): void
    {
        $this->postJson('/api/auth/check-phone', ['phone' => '0754123456'])->assertStatus(422);
    }

    public function test_handle_availability_reports_true_for_a_free_valid_handle(): void
    {
        $this->getJson('/api/sellers/handle-availability?handle=kariakoo_mobile')
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_handle_availability_reports_false_for_a_taken_handle(): void
    {
        SellerProfile::factory()->create(['handle' => 'amina_shop']);

        $this->getJson('/api/sellers/handle-availability?handle=amina_shop')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_handle_availability_reports_false_for_a_reserved_word(): void
    {
        $this->getJson('/api/sellers/handle-availability?handle=admin')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_handle_availability_reports_false_for_an_invalid_format(): void
    {
        $this->getJson('/api/sellers/handle-availability?handle=ab')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_registering_as_a_buyer_creates_a_user_with_no_seller_profile(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $response = $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'phone' => self::PHONE,
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
        ]);
        $user = User::where('phone', self::PHONE)->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame('1.0', $user->terms_version);
        $this->assertFalse($user->isSeller());
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_registering_as_a_seller_creates_a_user_and_a_pending_seller_profile_together(): void
    {
        $category = Category::factory()->create();
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Baraka Seller',
            'account_intent' => 'sell',
            'terms_version' => '1.0',
            'shop_name' => 'Baraka Electronics',
            'handle' => 'baraka_electronics',
            'category_id' => $category->id,
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'address' => 'Mwenge Road',
            'whatsapp' => '+255754999888',
        ])->assertOk();

        $user = User::where('phone', self::PHONE)->firstOrFail();
        $this->assertTrue($user->isSeller());
        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $user->id,
            'handle' => 'baraka_electronics',
            'shop_name' => 'Baraka Electronics',
            'status' => 'pending',
        ]);
    }

    public function test_registering_a_seller_with_a_taken_handle_is_rejected_and_creates_nothing(): void
    {
        SellerProfile::factory()->create(['handle' => 'taken_handle']);
        $category = Category::factory()->create();
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Baraka Seller',
            'account_intent' => 'sell',
            'terms_version' => '1.0',
            'shop_name' => 'Baraka Electronics',
            'handle' => 'taken_handle',
            'category_id' => $category->id,
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'address' => 'Mwenge Road',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    public function test_registering_a_seller_without_shop_fields_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Baraka Seller',
            'account_intent' => 'sell',
            'terms_version' => '1.0',
        ])->assertStatus(422);
    }

    public function test_registering_with_the_wrong_code_creates_nothing(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => '000000',
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    public function test_registering_an_already_registered_phone_is_rejected(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertStatus(422);
    }

    /** C6: opt-in consent checkbox at signup. */
    public function test_registering_with_marketing_consent_checked_records_it(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Amina Buyer',
            'marketing_consent' => true,
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertOk();

        $this->assertTrue(User::where('phone', self::PHONE)->firstOrFail()->marketing_consent);
    }

    public function test_registering_without_specifying_consent_defaults_to_false(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertOk();

        $this->assertFalse(User::where('phone', self::PHONE)->firstOrFail()->marketing_consent);
    }

    public function test_a_buyer_registration_rejects_stray_shop_fields(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->postJson('/api/auth/register', [
            'phone' => self::PHONE,
            'code' => $code,
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
            'shop_name' => 'Should not be here',
        ])->assertStatus(422);
    }
}

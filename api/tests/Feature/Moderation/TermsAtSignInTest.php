<?php

namespace Tests\Feature\Moderation;

use App\Models\Order;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Apple Guideline 1.2: users agree to the Terms before signing in or
 * registering. From 1.0 (9) the app shows the Terms step first and sends
 * `terms_version` with the sign-in request; the account records it in
 * that same request. Older builds send nothing and must keep working.
 */
class TermsAtSignInTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW_PHONE = '+255783029233';

    private const REVIEW_PASSWORD = 'Review2026';

    private const REVIEW_CODE = '246810';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.review_account.phone' => self::REVIEW_PHONE,
            'services.review_account.code' => self::REVIEW_CODE,
            'services.review_account.password' => self::REVIEW_PASSWORD,
        ]);
    }

    private function reviewUser(): User
    {
        // Exactly the state the live review account is in today: Terms 1.0.
        return User::factory()->create([
            'phone' => self::REVIEW_PHONE,
            'username' => 'appreview',
            'password' => self::REVIEW_PASSWORD,
            'terms_accepted_at' => now()->subMonth(),
            'terms_version' => '1.0',
        ]);
    }

    public function test_the_review_account_signs_in_on_the_first_screen_and_records_terms_1_1(): void
    {
        $user = $this->reviewUser();

        $response = $this->postJson('/api/auth/login', [
            'login' => '0783 029 233',
            'password' => self::REVIEW_PASSWORD,
            'terms_version' => '1.1',
        ])->assertOk();

        $response->assertJsonStructure(['token', 'user' => ['id']]);
        $this->assertArrayNotHasKey('requires_code', $response->json());
        $response->assertJsonPath('user.terms_version', '1.1');
        $response->assertJsonPath('user.terms_accepted', true);
        $this->assertSame('1.1', $user->fresh()->terms_version);
        $this->assertTrue($user->fresh()->terms_accepted_at->isToday());
    }

    public function test_the_review_account_also_signs_in_by_username_and_with_the_fixed_code(): void
    {
        $user = $this->reviewUser();

        $this->postJson('/api/auth/login', [
            'login' => 'appreview',
            'password' => self::REVIEW_PASSWORD,
            'terms_version' => '1.1',
        ])->assertOk()->assertJsonPath('user.terms_version', '1.1');

        $user->forceFill(['terms_version' => '1.0'])->save();
        $this->postJson('/api/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();
        $this->postJson('/api/auth/otp/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => self::REVIEW_CODE,
            'terms_version' => '1.1',
        ])->assertOk()->assertJsonPath('user.terms_version', '1.1');
        $this->assertNull(Cache::get('otp:'.self::REVIEW_PHONE));
    }

    public function test_a_build_7_sign_in_without_terms_version_still_works_and_changes_nothing(): void
    {
        $user = $this->reviewUser();

        $this->postJson('/api/auth/login', [
            'login' => '+255783029233',
            'password' => self::REVIEW_PASSWORD,
        ])->assertOk()->assertJsonPath('user.terms_version', '1.0');

        $this->assertSame('1.0', $user->fresh()->terms_version);
    }

    public function test_a_new_account_made_by_the_phone_code_sign_in_records_the_terms_it_agreed_to(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => '+255754999111'])->assertOk();
        $this->postJson('/api/auth/otp/verify', [
            'phone' => '+255754999111',
            'code' => Cache::get('otp:+255754999111'),
            'name' => 'Asha',
            'terms_version' => '1.1',
        ])->assertOk()
            ->assertJsonPath('is_new_account', true)
            ->assertJsonPath('user.terms_version', '1.1');
    }

    public function test_a_silly_terms_version_is_ignored(): void
    {
        $user = $this->reviewUser();

        $this->postJson('/api/auth/login', [
            'login' => 'appreview',
            'password' => self::REVIEW_PASSWORD,
            'terms_version' => str_repeat('9', 50),
        ])->assertOk();

        $this->assertSame('1.0', $user->fresh()->terms_version);
    }

    public function test_the_website_now_asks_for_terms_1_1(): void
    {
        $this->assertSame('1.1', \App\Support\Legal::TERMS_VERSION);
    }

    /** The public review list used to carry every reviewer's phone, email and username (AUDIT.md §5.1). */
    public function test_public_reviews_carry_no_private_details_but_still_parse_in_build_7(): void
    {
        $shop = SellerProfile::factory()->verified()->create(['handle' => 'dukani']);
        $buyer = User::factory()->create(['phone' => '+255712000111', 'email' => 'buyer@example.com', 'username' => 'buyer1']);
        $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id, 'seller_id' => $shop->id]);
        Review::factory()->create(['order_id' => $order->id, 'buyer_id' => $buyer->id, 'seller_id' => $shop->id]);

        $reviewer = $this->getJson('/api/sellers/dukani/reviews')->assertOk()->json('data.0.buyer');

        $this->assertSame($buyer->id, $reviewer['id']);
        $this->assertSame($buyer->name, $reviewer['name']);
        // iOS 1.0 (7) requires these two keys to parse a user.
        $this->assertIsBool($reviewer['is_seller']);
        $this->assertIsBool($reviewer['terms_accepted']);
        foreach (['phone', 'email', 'username', 'notify_orders', 'two_factor_enabled'] as $private) {
            $this->assertArrayNotHasKey($private, $reviewer);
        }
    }
}

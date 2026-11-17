<?php

namespace Tests\Feature\Returns;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's return policy: set by the seller, shown on the shop page, the
 * product page and at checkout before payment, frozen on the order. And
 * none of it exists for anyone outside the returns launch.
 */
class ReturnPolicyTest extends TestCase
{
    use RefreshDatabase;
    use ReturnsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
    }

    public function test_existing_shops_default_to_no_returns(): void
    {
        $shop = SellerProfile::factory()->verified()->create();

        $this->assertSame('none', $shop->fresh()->return_policy);
    }

    public function test_the_seller_sets_the_policy_and_it_shows_on_shop_product_and_checkout(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create(['seller_id' => $shop->id]);
        $buyer = User::factory()->create();

        $this->actingAs($shop->user)->putJson('/api/shop/return-policy', ['return_policy' => '2w'])->assertUnprocessable();
        $this->actingAs($shop->user)->putJson('/api/shop/return-policy', ['return_policy' => '3d', 'return_notes' => 'Unopened only'])
            ->assertOk()
            ->assertJsonPath('data.code', '3d')
            ->assertJsonPath('data.hours', 72)
            ->assertJsonPath('data.notes', 'Unopened only');

        $this->actingAs($buyer)->getJson("/api/sellers/{$shop->handle}")
            ->assertJsonPath('data.return_policy.code', '3d')
            ->assertJsonPath('data.return_policy.label', __('returns.policy.3d'));
        $this->actingAs($buyer)->getJson("/api/products/{$product->id}")
            ->assertJsonPath('data.return_policy.code', '3d');
        $this->actingAs($buyer)->getJson('/api/delivery/quote?'.http_build_query(['seller_id' => $shop->id, 'lat' => self::DROP_LAT, 'lng' => self::DROP_LNG]))
            ->assertJsonPath('data.return_policy.code', '3d')
            ->assertJsonPath('data.return_policy.notes', 'Unopened only');
    }

    public function test_the_policy_seen_at_checkout_is_the_one_that_applies(): void
    {
        $shop = $this->shop(['return_policy' => '7d']);
        $order = $this->placeDispatchOrder(shop: $shop);
        $this->assertSame('7d', $order->return_policy);

        $shop->forceFill(['return_policy' => 'none'])->save();
        $job = $this->bookedJob(shop: $shop);
        $this->assertSame('none', $job->order->return_policy, 'a new order gets the new policy');
        $this->assertSame('7d', $order->fresh()->return_policy, 'the earlier order keeps the policy it was bought under');
    }

    public function test_nobody_outside_the_returns_launch_sees_a_policy_key_or_a_return_route(): void
    {
        $shop = $this->shop(['return_policy' => '3d']);
        $product = Product::factory()->create(['seller_id' => $shop->id]);
        $guest = fn () => $this->app['auth']->forgetGuards();

        config(['delivery.returns_enabled' => false]);
        $this->getJson("/api/sellers/{$shop->handle}")->assertOk()->assertJsonMissingPath('data.return_policy');
        $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonMissingPath('data.return_policy');
        $this->actingAs(User::factory()->create())->getJson("/api/sellers/{$shop->handle}")->assertJsonMissingPath('data.return_policy');
        $this->actingAs($shop->user)->putJson('/api/shop/return-policy', ['return_policy' => '7d'])->assertNotFound();
        $this->actingAs($shop->user)->getJson('/api/returns')->assertNotFound();
        $this->assertSame('3d', $shop->fresh()->return_policy);
        $guest();
        $this->getJson('/api/features')->assertExactJson(['data' => ['delivery_enabled' => false, 'returns_enabled' => false]]);

        // Returns on, but this user is outside BOLT_TEST_PHONES.
        config(['delivery.returns_enabled' => true, 'delivery.test_phones' => '+255700000001']);
        $stranger = User::factory()->create(['phone' => '+255711999999']);
        $this->actingAs($stranger)->getJson("/api/sellers/{$shop->handle}")->assertJsonMissingPath('data.return_policy');
        $this->actingAs($stranger)->getJson('/api/returns')->assertNotFound();

        // A guest never sees it.
        config(['delivery.test_phones' => '']);
        $guest();
        $this->getJson("/api/sellers/{$shop->handle}")->assertJsonMissingPath('data.return_policy');
    }

    public function test_the_app_store_review_account_never_sees_returns(): void
    {
        config(['services.review_account.phone' => '+255783029233', 'delivery.test_phones' => '+255783029233']);
        $review = User::factory()->create(['phone' => '+255783029233']);
        $shop = $this->shop(['return_policy' => '3d']);

        $this->actingAs($review)->getJson('/api/features')->assertJsonPath('data.returns_enabled', false)->assertJsonMissingPath('data.returns_terms_version');
        $this->actingAs($review)->getJson("/api/sellers/{$shop->handle}")->assertJsonMissingPath('data.return_policy');
        $this->actingAs($review)->getJson('/api/returns')->assertNotFound();
    }

    public function test_agreeing_to_the_return_policy_never_touches_terms_version(): void
    {
        $user = User::factory()->create(['terms_version' => '1.1', 'terms_accepted_at' => now()->subMonth()]);

        $this->actingAs($user)->getJson('/api/features')
            ->assertJsonPath('data.returns_terms_version', '1.2')
            ->assertJsonPath('data.returns_terms_accepted', false);
        $this->actingAs($user)->postJson('/api/returns/terms/accept', ['version' => '1.1'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/returns/terms/accept', ['version' => Legal::RETURNS_TERMS_VERSION])->assertOk();

        $user->refresh();
        $this->assertSame('1.1', $user->terms_version, 'iOS 1.0 (9) compares this with "1.1": it must never change');
        $this->assertSame('1.2', $user->returns_terms_version);
        $this->actingAs($user)->getJson('/api/auth/me')->assertJsonPath('data.terms_version', '1.1');
        $this->actingAs($user)->getJson('/api/features')->assertJsonPath('data.returns_terms_accepted', true);
    }

    public function test_the_terms_page_is_exactly_1_1_until_returns_are_switched_on(): void
    {
        config(['delivery.returns_enabled' => false]);
        $page = $this->get('/terms')->assertOk()->getContent();
        $this->assertStringContainsString('toleo 1.1', $page, 'Swahili is the default');
        $this->assertStringNotContainsString('return-policy', $page);

        config(['delivery.returns_enabled' => true]);
        $page = $this->get('/terms')->assertOk()->getContent();
        $this->assertStringContainsString('toleo 1.2', $page);
        $this->assertStringContainsString('Sera ya Kurudisha bidhaa', $page);

        $english = $this->get('/terms?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString('version 1.2', $english);
        $this->assertStringContainsString('14. Return Policy', $english);
        $this->assertStringContainsString('Zero tolerance', $english, 'clause 2 is unchanged');
    }
}

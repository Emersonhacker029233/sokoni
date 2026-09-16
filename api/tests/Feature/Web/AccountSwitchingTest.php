<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Part 5 (client feedback): "Instagram-style account switching... The
 * same behaviour using multiple sessions, if it can be done cleanly."
 * The website has no cart and no per-user PHP session state at all
 * (favourites/orders/messages are DB relations keyed by `Auth::id()`),
 * so — unlike the app — there's no separate cache to prove doesn't leak;
 * proving the switch correctly changes which user's DB-backed data is
 * visible is the whole test. See WebAccountSwitcher for the mechanics.
 */
class AccountSwitchingTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ], $attributes));
    }

    public function test_visiting_login_while_authenticated_redirects_to_the_dashboard(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAsWebUser($user)->get('/login');

        $response->assertRedirect(route('web.account.dashboard'));
    }

    public function test_visiting_login_with_add_account_while_authenticated_shows_the_form_instead(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAsWebUser($user)->get('/login?add_account=1');

        $response->assertOk();
        $response->assertSee(__('site.auth_add_account_title'));
    }

    /**
     * The actual Part 5 scenario: signed in as A, verify a second phone
     * number without signing out first — A must still be reachable
     * afterwards (see the next test), and B becomes the active session.
     */
    public function test_verifying_a_second_phone_while_signed_in_adds_and_switches_to_it(): void
    {
        $userA = $this->onboardedUser(['name' => 'Amina']);

        $this->actingAsWebUser($userA)->get('/login?add_account=1');
        $this->post('/auth/otp/request', ['phone' => '+255754111222']);
        $code = Cache::get('otp:+255754111222');
        $this->assertNotNull($code);

        $response = $this->post('/auth/otp/verify', ['phone' => '+255754111222', 'code' => $code, 'name' => 'Baraka']);

        $response->assertRedirect(route('web.account.dashboard'));
        $userB = User::where('phone', '+255754111222')->firstOrFail();
        $this->assertAuthenticatedAs($userB, 'web');
    }

    public function test_switching_back_to_the_account_added_from_shows_its_own_data_not_the_others(): void
    {
        $userA = $this->onboardedUser(['name' => 'Amina']);
        $userB = $this->onboardedUser(['name' => 'Baraka']);
        $productA = Product::factory()->for(SellerProfile::factory(), 'seller')->create(['title' => 'Amina Special']);
        $productB = Product::factory()->for(SellerProfile::factory(), 'seller')->create(['title' => 'Baraka Special']);
        $userA->favorites()->attach($productA->id);
        $userB->favorites()->attach($productB->id);

        // Sign in as A, then "Add account" B — B ends up active, A stays
        // linked. `actingAsWebUser()` is a test shortcut that sets the
        // guard directly rather than going through the real sign-in
        // controller, so it never adds A to `linked_account_ids` the way
        // an actual OTP verify would — seed it here to match what a real
        // "already signed in as A" session looks like.
        $this->withSession(['linked_account_ids' => [$userA->id]]);
        $this->actingAsWebUser($userA)->get('/login?add_account=1');
        $this->post('/auth/otp/request', ['phone' => $userB->phone]);
        $code = Cache::get('otp:'.$userB->phone);
        $this->post('/auth/otp/verify', ['phone' => $userB->phone, 'code' => $code, 'name' => $userB->name]);
        $this->assertAuthenticatedAs($userB, 'web');

        $savedAsB = $this->get(route('web.account.saved'));
        $savedAsB->assertSee('Baraka Special');
        $savedAsB->assertDontSee('Amina Special');

        // Switch back to A without any re-verification.
        $switchBack = $this->post(route('web.account.switch', $userA));
        $switchBack->assertRedirect(route('web.account.dashboard'));
        $this->assertAuthenticatedAs($userA, 'web');

        $savedAsA = $this->get(route('web.account.saved'));
        $savedAsA->assertSee('Amina Special');
        $savedAsA->assertDontSee('Baraka Special');
    }

    /**
     * The switcher must never accept an arbitrary user id — only one
     * this exact browser session already verified via OTP/Google earlier
     * in the same session (see WebAccountSwitcher::switchTo()).
     */
    public function test_switching_to_an_account_never_linked_in_this_session_is_refused(): void
    {
        $userA = $this->onboardedUser();
        $stranger = $this->onboardedUser();

        $response = $this->actingAsWebUser($userA)->post(route('web.account.switch', $stranger));

        $response->assertRedirect(route('web.account.dashboard'));
        $this->assertAuthenticatedAs($userA, 'web');
    }

    /**
     * Part 5 (client feedback): "Signing out removes only the active
     * account and returns to the next one, or to guest if it was the
     * last."
     */
    public function test_signing_out_of_one_of_two_linked_accounts_returns_to_the_other_not_to_guest(): void
    {
        $userA = $this->onboardedUser(['name' => 'Amina']);
        $userB = $this->onboardedUser(['name' => 'Baraka']);

        // Same shortcut-vs-real-flow gap as the test above — seed A into
        // the linked list `actingAsWebUser()` bypasses.
        $this->withSession(['linked_account_ids' => [$userA->id]]);
        $this->actingAsWebUser($userA)->get('/login?add_account=1');
        $this->post('/auth/otp/request', ['phone' => $userB->phone]);
        $code = Cache::get('otp:'.$userB->phone);
        $this->post('/auth/otp/verify', ['phone' => $userB->phone, 'code' => $code, 'name' => $userB->name]);
        $this->assertAuthenticatedAs($userB, 'web');

        $response = $this->post(route('web.logout'));

        $response->assertRedirect(route('web.home'));
        $this->assertAuthenticatedAs($userA, 'web');
    }

    public function test_signing_out_of_the_only_linked_account_returns_to_guest(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAsWebUser($user)->post(route('web.logout'));

        $response->assertRedirect(route('web.home'));
        $this->assertGuest('web');
    }
}

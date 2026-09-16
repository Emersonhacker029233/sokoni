<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+255754123456';

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    /**
     * Part 2 (client feedback): "+255 shown as static text, not editable
     * and not deletable" — a fixed prefix segment plus a hidden `phone`
     * input Alpine composes the full E.164 value into, not an editable
     * `+255...` example baked into the visible field itself.
     */
    public function test_the_login_page_shows_a_fixed_255_prefix_not_an_editable_one(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('>+255<', false);
        $response->assertSee('id="phone_local"', false);
        $response->assertSee('type="hidden" name="phone"', false);
        // The old markup let the visitor type the full +255... themselves.
        $response->assertDontSee('placeholder="+255754123456"', false);
    }

    /** Part 2 (client feedback): "update the validation message so it describes the local format the user is actually entering." */
    public function test_an_invalid_phone_shows_a_message_describing_the_local_format(): void
    {
        $response = $this->post('/auth/otp/request', ['phone' => '+255123456789']);

        $response->assertSessionHasErrors(['phone' => 'Enter a valid Tanzanian mobile number, e.g. 712 345 678 or 0712 345 678.']);
    }

    public function test_a_new_visitor_can_register_and_is_walked_through_terms_and_intent(): void
    {
        $this->post('/auth/otp/request', ['phone' => self::PHONE])->assertRedirect('/login');
        $code = Cache::get('otp:'.self::PHONE);
        $this->assertNotNull($code);

        $response = $this->post('/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina']);
        $response->assertRedirect(route('web.account.dashboard'));

        $this->assertAuthenticated('web');
        $user = User::where('phone', self::PHONE)->first();
        $this->assertNotNull($user);

        // Terms not yet accepted — every subsequent page redirects to it.
        $this->get(route('web.account.dashboard'))->assertRedirect(route('web.auth.terms'));

        $this->post(route('web.auth.terms.store'), ['accepted' => '1'])->assertRedirect(route('web.auth.intent'));

        $user->refresh();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(\App\Support\Legal::TERMS_VERSION, $user->terms_version);

        // Intent not yet chosen — still redirected.
        $this->get(route('web.account.dashboard'))->assertRedirect(route('web.auth.intent'));

        $this->post(route('web.auth.intent.store'), ['intent' => 'buy'])->assertRedirect(route('web.account.dashboard'));

        $this->assertSame('buy', $user->fresh()->account_intent);
        $this->get(route('web.account.dashboard'))->assertOk();
    }

    /** C6: opt-in consent checkbox shown on the same new-account form. */
    public function test_checking_marketing_consent_at_signup_records_it(): void
    {
        $this->post('/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->post('/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina', 'marketing_consent' => '1']);

        $this->assertTrue(User::where('phone', self::PHONE)->firstOrFail()->marketing_consent);
    }

    public function test_leaving_marketing_consent_unchecked_at_signup_defaults_to_false(): void
    {
        $this->post('/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->post('/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, 'name' => 'Amina']);

        $this->assertFalse(User::where('phone', self::PHONE)->firstOrFail()->marketing_consent);
    }

    public function test_choosing_sell_intent_redirects_to_the_shop_dashboard(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'terms_accepted_at' => now(), 'terms_version' => \App\Support\Legal::TERMS_VERSION]);
        $this->actingAsWebUser($user);

        $this->post(route('web.auth.intent.store'), ['intent' => 'sell'])->assertRedirect(route('web.account.shop'));
    }

    public function test_a_banned_user_cannot_verify_otp(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'banned_at' => now()]);
        $this->post('/auth/otp/request', ['phone' => self::PHONE]);
        $code = Cache::get('otp:'.self::PHONE);

        $this->post('/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_logout_clears_the_web_session(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now(), 'terms_version' => \App\Support\Legal::TERMS_VERSION, 'account_intent' => 'buy']);
        $this->actingAsWebUser($user);

        $this->post(route('web.logout'))->assertRedirect(route('web.home'));
        $this->assertGuest('web');
    }
}

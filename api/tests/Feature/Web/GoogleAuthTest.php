<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\Auth\GoogleAuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * C1 (tester feedback): "complete Google OAuth sign-in flow." The
 * controller itself (redirect()/callback()) was already fully built —
 * this suite proves it end to end and fixes one real bug found while
 * verifying it: the login page decided whether to show the button by
 * checking only `GOOGLE_CLIENT_ID`, but the redirect route 404s unless
 * client_id + client_secret + redirect are ALL set (GoogleAuthController::
 * isConfigured()) — a config with only client_id set (e.g. the app's
 * native flow configured, not the website's) would have shown a button
 * that then 404'd on click.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function unconfigure(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null, 'services.google.redirect' => null]);
    }

    private function configureFully(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://sokoni.test/auth/google/callback',
        ]);
    }

    public function test_the_login_page_hides_the_google_button_when_nothing_is_configured(): void
    {
        $this->unconfigure();

        $response = $this->get(route('web.login'));

        $response->assertOk();
        $response->assertDontSee(route('web.auth.google.redirect'), false);
    }

    /**
     * The actual bug: only client_id set (client_secret/redirect missing)
     * must still hide the button, not show one that 404s on click.
     */
    public function test_the_login_page_hides_the_google_button_when_only_client_id_is_set(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $response = $this->get(route('web.login'));

        $response->assertOk();
        $response->assertDontSee(route('web.auth.google.redirect'), false);
    }

    public function test_the_login_page_shows_the_google_button_when_fully_configured(): void
    {
        $this->configureFully();

        $response = $this->get(route('web.login'));

        $response->assertOk();
        $response->assertSee(route('web.auth.google.redirect'), false);
    }

    public function test_is_configured_requires_all_three_values(): void
    {
        $this->unconfigure();
        $this->assertFalse(GoogleAuthController::isConfigured());

        $this->configureFully();
        $this->assertTrue(GoogleAuthController::isConfigured());

        config(['services.google.redirect' => null]);
        $this->assertFalse(GoogleAuthController::isConfigured());
    }

    public function test_the_redirect_route_404s_when_not_fully_configured(): void
    {
        $this->unconfigure();

        $this->get(route('web.auth.google.redirect'))->assertNotFound();
    }

    public function test_the_callback_route_404s_when_not_fully_configured(): void
    {
        $this->unconfigure();

        $this->get(route('web.auth.google.callback'))->assertNotFound();
    }

    public function test_the_redirect_route_hands_off_to_google_when_configured(): void
    {
        $this->configureFully();
        Socialite::shouldReceive('driver->redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        $response = $this->get(route('web.auth.google.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_a_successful_callback_signs_in_a_new_user_and_lands_on_the_dashboard(): void
    {
        $this->configureFully();

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('newseller@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('New Seller');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

        $response = $this->get(route('web.auth.google.callback'));

        $response->assertRedirect(route('web.account.dashboard'));
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'newseller@example.com', 'provider' => 'google', 'provider_id' => 'google-123']);
    }

    public function test_a_banned_users_callback_is_rejected_with_an_error_not_signed_in(): void
    {
        $this->configureFully();
        $banned = User::factory()->create(['provider' => 'google', 'provider_id' => 'google-999', 'banned_at' => now()]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-999');
        $socialiteUser->shouldReceive('getEmail')->andReturn($banned->email);
        $socialiteUser->shouldReceive('getName')->andReturn($banned->name);
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

        $response = $this->get(route('web.auth.google.callback'));

        $response->assertRedirect(route('web.login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest('web');
    }

    public function test_a_failed_socialite_exchange_redirects_to_login_with_an_error(): void
    {
        $this->configureFully();
        Socialite::shouldReceive('driver->user')->once()->andThrow(new \Exception('invalid_grant'));

        $response = $this->get(route('web.auth.google.callback'));

        $response->assertRedirect(route('web.login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest('web');
    }
}

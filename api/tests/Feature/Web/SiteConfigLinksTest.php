<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteConfigLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_icons_are_hidden_from_the_footer_when_unset(): void
    {
        config(['sokoni.social' => ['facebook' => null, 'instagram' => null, 'x' => null]]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('aria-label="Facebook"', false);
        $response->assertDontSee('aria-label="Instagram"', false);
        $response->assertDontSee('aria-label="X (Twitter)"', false);
    }

    public function test_social_icons_appear_and_link_to_configured_urls(): void
    {
        config(['sokoni.social' => [
            'facebook' => 'https://facebook.com/realsokoni',
            'instagram' => null,
            'x' => null,
        ]]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://facebook.com/realsokoni', false);
        $response->assertDontSee('aria-label="Instagram"', false);
    }

    public function test_google_play_link_and_app_download_banner_are_hidden_when_unset(): void
    {
        config(['sokoni.app_links' => ['google_play' => null]]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('id="app-download"', false);
        $response->assertDontSee('Google Play', false);
    }

    public function test_google_play_link_appears_when_configured(): void
    {
        config(['sokoni.app_links' => ['google_play' => 'https://play.google.com/store/apps/details?id=tz.co.sokoni']]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://play.google.com/store/apps/details?id=tz.co.sokoni', false);
    }

    public function test_the_app_store_link_never_appears_anywhere_on_the_site(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('apps.apple.com', false);
        $response->assertDontSee('App Store', false);
    }

    public function test_contact_and_legal_pages_use_the_configured_support_email(): void
    {
        config(['sokoni.support_email' => 'help@sokoni.co.tz']);

        $this->get('/contact')->assertOk()->assertSee('help@sokoni.co.tz', false);
        $this->get('/privacy')->assertOk()->assertSee('help@sokoni.co.tz', false);
        $this->get('/terms')->assertOk()->assertSee('help@sokoni.co.tz', false);
    }
}

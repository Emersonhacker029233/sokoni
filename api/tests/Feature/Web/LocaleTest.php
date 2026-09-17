<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Language audit (client feedback): "Kiswahili is the primary language
 * of this marketplace's users... it must be what a visitor sees first...
 * do not default to English just because a browser is configured in the
 * United States." SetWebLocale used to do the opposite of all of this —
 * these tests pin down the corrected behaviour so it can't regress back.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_with_no_stored_preference_sees_kiswahili(): void
    {
        // Laravel's own test HTTP client always sends
        // "en-US,en;q=0.5" by default (there's no way to send a request
        // with literally no Accept-Language header at all through it) —
        // which conveniently doubles as the exact "en-US" scenario the
        // client's brief itself calls out, so this also covers a real
        // browser sending that same default.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(__('site.home_hero_title', [], 'sw'));
        $response->assertDontSee(__('site.home_hero_title', [], 'en'));
    }

    public function test_a_browser_configured_for_the_united_states_still_defaults_to_kiswahili(): void
    {
        // The exact scenario named in the brief: "en-US" is a real,
        // common Accept-Language value that says nothing about whether
        // this visitor actually wants an English-language marketplace.
        $response = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/');

        $response->assertOk();
        $response->assertSee(__('site.home_hero_title', [], 'sw'));
    }

    public function test_a_clearly_english_browser_gets_english(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])->get('/');

        $response->assertOk();
        $response->assertSee(__('site.home_hero_title', [], 'en'));
    }

    public function test_a_swahili_accept_language_header_also_gets_kiswahili(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'sw-TZ,sw;q=0.9'])->get('/');

        $response->assertOk();
        $response->assertSee(__('site.home_hero_title', [], 'sw'));
    }

    public function test_choosing_english_is_remembered_on_the_next_request_even_with_no_english_header(): void
    {
        $this->get('/?lang=en')->assertOk();

        // A later request with NO Accept-Language hint at all — the
        // stored choice must still win over the Kiswahili default.
        $response = $this->get('/');

        $response->assertSee(__('site.home_hero_title', [], 'en'));
    }

    public function test_an_explicit_choice_overrides_what_the_browser_header_would_otherwise_suggest(): void
    {
        $this->get('/?lang=en')->assertOk();

        // Same browser now sends a Swahili-looking header — the stored
        // preference must still be respected "above everything else".
        $response = $this->withHeaders(['Accept-Language' => 'sw-TZ'])->get('/');

        $response->assertSee(__('site.home_hero_title', [], 'en'));
    }

    public function test_the_default_variant_declared_in_hreflang_tags_is_kiswahili(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('hreflang="x-default" href="'.url('/').'?lang=sw"', false);
        $response->assertSee('hreflang="sw" href="'.url('/').'?lang=sw"', false);
        $response->assertSee('hreflang="en" href="'.url('/').'?lang=en"', false);
    }

    /**
     * Order::labelForStatus()/statusLabel()/deliveryMethodLabel() —
     * these replaced raw ucfirst($enum) rendering that was always
     * English regardless of the site's language (language audit).
     */
    public function test_order_status_and_delivery_method_labels_switch_with_the_app_locale(): void
    {
        $order = Order::factory()->create(['status' => 'ready', 'delivery_method' => 'delivery']);

        App::setLocale('en');
        $this->assertSame('Ready', $order->statusLabel());
        $this->assertSame('Delivery', $order->deliveryMethodLabel());
        $this->assertSame('Accepted', Order::labelForStatus('accepted'));

        App::setLocale('sw');
        $this->assertSame('Tayari', $order->statusLabel());
        $this->assertSame('Kuletewa', $order->deliveryMethodLabel());
        $this->assertSame('Imekubaliwa', Order::labelForStatus('accepted'));
    }

    public function test_seller_status_label_switches_with_the_app_locale(): void
    {
        $seller = SellerProfile::factory()->create(['status' => 'rejected']);

        App::setLocale('en');
        $this->assertSame('Rejected', $seller->statusLabel());

        App::setLocale('sw');
        $this->assertSame('Imekataliwa', $seller->statusLabel());
    }

    /** Drives which language Order/SellerVerification/etc. notifications render in — see User::preferredLocale(). */
    public function test_a_user_with_no_explicit_locale_prefers_kiswahili(): void
    {
        // An in-memory instance, deliberately not persisted — the
        // `users.locale` column is NOT NULL with a database-level
        // default, so this exercises preferredLocale()'s own `?? 'sw'`
        // fallback directly rather than the column default.
        $this->assertSame('sw', (new User())->preferredLocale());
    }

    public function test_a_users_explicit_locale_is_respected(): void
    {
        $user = User::factory()->make(['locale' => 'en']);

        $this->assertSame('en', $user->preferredLocale());
    }
}

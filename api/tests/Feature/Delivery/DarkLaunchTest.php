<?php

namespace Tests\Feature\Delivery;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

/**
 * The dark launch (docs/delivery/BOLT.md, "Flags"): iOS 1.0 (9) in App
 * Review and Android 1.0.0 in the store use the production API, so with
 * the flags off, or for anyone outside BOLT_TEST_PHONES, or for the App
 * Store review account, nothing may look or behave differently.
 */
class DarkLaunchTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    private const REVIEW_PHONE = '+255783029233';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
        config(['services.review_account.phone' => self::REVIEW_PHONE, 'services.review_account.password' => 'Review2026']);
    }

    private function dispatchCheckout(User $buyer, Product $product)
    {
        return $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'delivery_method' => 'delivery',
            'address' => 'Mtaa wa Uhuru 12',
            'delivery_lat' => self::DROP_LAT,
            'delivery_lng' => self::DROP_LNG,
            'payment_method' => 'cash_on_delivery',
            'fulfilment' => 'dispatch',
        ]);
    }

    public function test_with_the_flags_off_everything_is_as_before(): void
    {
        config(['delivery.enabled' => false, 'delivery.returns_enabled' => false]);
        $buyer = User::factory()->create(['phone' => '+255711000111']);

        $this->getJson('/api/features')->assertOk()->assertExactJson(['data' => ['delivery_enabled' => false, 'returns_enabled' => false]]);
        $this->actingAs($buyer)->getJson('/api/features')->assertJsonPath('data.delivery_enabled', false);

        foreach (['/api/delivery/config', '/api/delivery/quote?seller_id=1&lat=1&lng=1', '/api/delivery/orders/1'] as $uri) {
            // Indistinguishable from a route that does not exist.
            $path = ltrim(parse_url($uri, PHP_URL_PATH), '/');
            $this->actingAs($buyer)->getJson($uri)->assertNotFound()->assertJsonPath('message', "The route {$path} could not be found.");
        }
        $this->postJson('/api/delivery/orders/1/pay')->assertNotFound(); // 404 even before sign-in
        $this->postJson('/api/webhooks/bolt', ['ride_id' => 1, 'state' => 'COMPLETED'])->assertNotFound();

        $product = Product::factory()->create(['seller_id' => $this->shop()->id, 'price' => 10000]);
        $this->dispatchCheckout($buyer, $product)
            ->assertCreated()
            ->assertJsonPath('data.delivery_fee', 3000)
            ->assertJsonMissingPath('data.fulfilment');
        $this->assertSame([], $this->boltFareIds);
    }

    public function test_the_allowlist_limits_delivery_to_test_phones_on_both_sides(): void
    {
        config(['delivery.test_phones' => '+255711000111, 0754 000 222']);
        $tester = User::factory()->create(['phone' => '+255711000111']);
        $stranger = User::factory()->create(['phone' => '+255711000999']);
        $testShop = $this->shop();
        $testShop->user->forceFill(['phone' => '+255754000222'])->save();
        $realShop = $this->shop(['shop_name' => 'Real shop']);

        $this->actingAs($tester)->getJson('/api/features')->assertJsonPath('data.delivery_enabled', true);
        $this->actingAs($stranger)->getJson('/api/features')->assertJsonPath('data.delivery_enabled', false);
        $this->actingAs($stranger)->getJson('/api/delivery/config')->assertNotFound();

        // A tester can't send a Bolt driver to a shop outside the test.
        $this->actingAs($tester)
            ->getJson('/api/delivery/quote?'.http_build_query(['seller_id' => $realShop->id, 'lat' => self::DROP_LAT, 'lng' => self::DROP_LNG]))
            ->assertStatus(422)->assertJsonPath('code', 'not_available');
        $this->dispatchCheckout($tester, Product::factory()->create(['seller_id' => $realShop->id]))
            ->assertCreated()->assertJsonMissingPath('data.fulfilment');

        // Between two testers it works.
        $this->dispatchCheckout($tester, Product::factory()->create(['seller_id' => $testShop->id]))
            ->assertCreated()->assertJsonPath('data.fulfilment', 'dispatch');

        // A stranger sending fulfilment gets an ordinary order, not a validation error.
        $this->dispatchCheckout($stranger, Product::factory()->create(['seller_id' => $testShop->id]))
            ->assertCreated()->assertJsonMissingPath('data.fulfilment');
    }

    public function test_the_app_store_review_account_never_sees_delivery_even_if_listed(): void
    {
        config(['delivery.test_phones' => self::REVIEW_PHONE, 'delivery.returns_enabled' => true]);
        $review = User::factory()->create(['phone' => self::REVIEW_PHONE, 'username' => 'appreview']);

        $this->actingAs($review)->getJson('/api/features')
            ->assertExactJson(['data' => ['delivery_enabled' => false, 'returns_enabled' => false]]);
        $this->actingAs($review)->getJson('/api/delivery/config')->assertNotFound();
        $this->actingAs($review)->getJson('/api/delivery/orders/1/location')->assertNotFound();

        config(['delivery.test_phones' => '']); // full launch: still never the review account
        $this->actingAs($review)->getJson('/api/delivery/orders/1/location')->assertNotFound();
        $this->actingAs($review)->getJson('/api/features')->assertJsonPath('data.delivery_enabled', false);

        $product = Product::factory()->create(['seller_id' => $this->shop()->id]);
        $this->dispatchCheckout($review, $product)->assertCreated()->assertJsonMissingPath('data.fulfilment');
        $this->assertNull(Order::query()->sole()->fulfilment);
    }

    public function test_a_review_account_shop_never_receives_bolt_orders(): void
    {
        $reviewShop = $this->shop();
        $reviewShop->user->forceFill(['phone' => self::REVIEW_PHONE])->save();

        $this->dispatchCheckout(User::factory()->create(), Product::factory()->create(['seller_id' => $reviewShop->id]))
            ->assertCreated()->assertJsonMissingPath('data.fulfilment');
    }

    /** routes/console.php read again into a fresh Schedule under $config: "command | cron | repeat seconds" per line. */
    private function scheduleWith(array $config): string
    {
        config($config);
        $schedule = new Schedule;
        $this->app->instance(Schedule::class, $schedule);
        Facade::clearResolvedInstances();
        require base_path('routes/console.php');

        return collect($schedule->events())->map(fn ($e) => $e->command.' | '.$e->expression.' | '.($e->repeatSeconds ?? '-'))->implode("\n");
    }

    public function test_without_bolt_the_scheduler_runs_exactly_what_it_did_before(): void
    {
        $schedule = $this->scheduleWith(['delivery.enabled' => false, 'delivery.bolt.client_id' => null]);

        $this->assertStringNotContainsString('delivery:', $schedule);
        $this->assertStringContainsString('reports:waiting-summary', $schedule, 'the existing schedule is untouched');
    }

    public function test_with_bolt_on_the_minute_tick_and_ten_second_tracker_are_scheduled(): void
    {
        $schedule = $this->scheduleWith(['delivery.enabled' => true, 'delivery.bolt.client_id' => 'x']);

        $this->assertMatchesRegularExpression('/delivery:tick.* \| \* \* \* \* \* \| -/', $schedule);
        $this->assertMatchesRegularExpression('/delivery:track.* \| \* \* \* \* \* \| 10/', $schedule);
    }

    public function test_credentials_alone_keep_the_cron_running_so_open_deliveries_finish_after_a_switch_off(): void
    {
        $schedule = $this->scheduleWith(['delivery.enabled' => false, 'delivery.bolt.client_id' => 'x']);

        $this->assertStringContainsString('delivery:tick', $schedule);
        $this->assertStringContainsString('delivery:track', $schedule);
    }
}

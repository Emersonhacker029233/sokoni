<?php

namespace Tests\Feature\Web;

use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use App\Support\OpeningHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sellers had no way to edit opening hours at all — only ever displayed
 * (tester feedback A6). `App\Support\OpeningHours`'s own docblock claims
 * an app "Edit Profile screen" already writes this shape, but grepping
 * the whole app confirms no such writer exists anywhere — this is
 * genuinely the first one, not a missing-feedback gap like reviews.
 */
class ShopHoursTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedSellerOwner(): array
    {
        $user = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'sell',
        ]);
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $user->id, 'opening_hours' => null]);

        return [$user, $seller];
    }

    private function payloadWithMondayClosedRestOpen(): array
    {
        $hours = [];
        foreach (OpeningHours::DAYS as $day) {
            $hours[$day] = $day === 'monday'
                ? ['closed' => '1']
                : ['open' => '09:00', 'close' => '17:00'];
        }

        return ['hours' => $hours];
    }

    public function test_the_dashboard_shows_an_hours_editor(): void
    {
        [$user, $seller] = $this->onboardedSellerOwner();

        $response = $this->actingAsWebUser($user)->get(route('web.account.shop'));

        $response->assertOk();
        $response->assertSee(route('web.account.shop.hours', $seller), false);
    }

    public function test_the_owner_can_set_hours_with_one_day_closed(): void
    {
        [$user, $seller] = $this->onboardedSellerOwner();

        $response = $this->actingAsWebUser($user)->post(
            route('web.account.shop.hours', $seller),
            $this->payloadWithMondayClosedRestOpen()
        );

        $response->assertRedirect();
        $seller->refresh();
        $this->assertNull($seller->opening_hours['monday']);
        $this->assertSame(['open' => '09:00', 'close' => '17:00'], $seller->opening_hours['tuesday']);

        $this->followRedirects($response)->assertSee(__('site.shop_hours_saved'));
    }

    public function test_a_non_closed_day_requires_valid_open_and_close_times(): void
    {
        [$user, $seller] = $this->onboardedSellerOwner();
        $payload = $this->payloadWithMondayClosedRestOpen();
        $payload['hours']['tuesday'] = ['open' => 'not-a-time', 'close' => '17:00'];

        $this->actingAsWebUser($user)
            ->post(route('web.account.shop.hours', $seller), $payload)
            ->assertSessionHasErrors('hours.tuesday.open');
    }

    public function test_close_time_must_be_after_open_time(): void
    {
        [$user, $seller] = $this->onboardedSellerOwner();
        $payload = $this->payloadWithMondayClosedRestOpen();
        $payload['hours']['tuesday'] = ['open' => '18:00', 'close' => '09:00'];

        $this->actingAsWebUser($user)
            ->post(route('web.account.shop.hours', $seller), $payload)
            ->assertSessionHasErrors('hours.tuesday.close');
    }

    public function test_a_stranger_cannot_edit_another_sellers_hours(): void
    {
        [, $seller] = $this->onboardedSellerOwner();
        $stranger = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);

        $this->actingAsWebUser($stranger)
            ->post(route('web.account.shop.hours', $seller), $this->payloadWithMondayClosedRestOpen())
            ->assertForbidden();
    }

    public function test_the_shop_page_shows_translated_day_names(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->get(route('web.shop', $seller->handle).'?lang=sw')
            ->assertOk()
            ->assertSee(__('site.day_monday', [], 'sw'));
    }
}

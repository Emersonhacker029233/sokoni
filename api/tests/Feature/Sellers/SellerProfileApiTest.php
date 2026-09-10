<?php

namespace Tests\Feature\Sellers;

use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D3 (tester feedback): the app's shop profile screen was missing opening
 * hours entirely — not a rendering gap, `SellerProfileResource` simply
 * never sent the field at all, unlike the website's shop page (which
 * reads `$seller->opening_hours` directly, server-side).
 */
class SellerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seller_show_endpoint_includes_the_full_seven_day_opening_hours_shape(): void
    {
        $seller = SellerProfile::factory()->verified()->create([
            'opening_hours' => [
                'monday' => ['open' => '08:00', 'close' => '18:00'],
                'sunday' => null,
            ],
        ]);

        $response = $this->getJson("/api/sellers/{$seller->handle}");

        $response->assertOk();
        $response->assertJsonPath('data.opening_hours.monday.open', '08:00');
        $response->assertJsonPath('data.opening_hours.monday.close', '18:00');
        $response->assertJsonPath('data.opening_hours.sunday', null);
        // Every day always present, even ones the column never set at all —
        // the app should never have to special-case a partially-filled column.
        foreach (['tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
            $response->assertJsonPath("data.opening_hours.{$day}", null);
        }
    }

    public function test_the_seller_show_endpoint_includes_a_closed_week_when_no_hours_are_set_at_all(): void
    {
        $seller = SellerProfile::factory()->verified()->create(['opening_hours' => null]);

        $response = $this->getJson("/api/sellers/{$seller->handle}");

        $response->assertOk();
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $response->assertJsonPath("data.opening_hours.{$day}", null);
        }
    }
}

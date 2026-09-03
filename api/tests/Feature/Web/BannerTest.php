<?php

namespace Tests\Feature\Web;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_in_date_home_hero_banner_renders_and_counts_an_impression(): void
    {
        $banner = Banner::factory()->create(['position' => 'home_hero', 'title' => 'A real campaign banner']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('A real campaign banner');
        $this->assertSame(1, $banner->fresh()->impressions_count);
    }

    public function test_an_inactive_banner_never_renders(): void
    {
        Banner::factory()->inactive()->create(['position' => 'home_hero', 'title' => 'Should never appear']);

        $this->get('/')->assertDontSee('Should never appear');
    }

    public function test_an_expired_banner_never_renders(): void
    {
        Banner::factory()->expired()->create(['position' => 'home_hero', 'title' => 'Should never appear']);

        $this->get('/')->assertDontSee('Should never appear');
    }

    public function test_a_scheduled_future_banner_never_renders_before_its_start_date(): void
    {
        Banner::factory()->create([
            'position' => 'home_hero',
            'title' => 'Should never appear yet',
            'starts_at' => now()->addWeek(),
        ]);

        $this->get('/')->assertDontSee('Should never appear yet');
    }

    public function test_an_empty_slot_renders_nothing_not_a_placeholder(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // No banner exists anywhere — the home_mid slot's own wrapper markup must not appear at all.
        $response->assertDontSee('web/banners', false);
    }

    public function test_only_the_matching_position_renders_in_its_slot(): void
    {
        Banner::factory()->create(['position' => 'category_top', 'title' => 'Category banner only']);

        $this->get('/')->assertDontSee('Category banner only');
    }

    public function test_clicking_a_banner_records_a_click_and_redirects_to_the_real_url(): void
    {
        $banner = Banner::factory()->create(['link_url' => 'https://example.com/campaign']);

        $response = $this->get(route('web.banners.click', $banner));

        $response->assertRedirect('https://example.com/campaign');
        $this->assertSame(1, $banner->fresh()->clicks_count);
    }
}

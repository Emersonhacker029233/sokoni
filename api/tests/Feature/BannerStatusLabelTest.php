<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 1 (client feedback, urgent): "an uploaded banner never appears" —
 * for some banners this turned out to be a schedule/is_active state the
 * admin table never said out loud. Mirrors Banner::scopeLive()'s own
 * exact rules, checked directly here so the two can never quietly drift
 * apart from each other.
 */
class BannerStatusLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_unscheduled_banner_is_live_now(): void
    {
        $banner = Banner::factory()->create(['is_active' => true, 'starts_at' => null, 'ends_at' => null]);

        $this->assertSame('Live now', $banner->liveStatusLabel());
    }

    public function test_an_inactive_banner_says_inactive_regardless_of_schedule(): void
    {
        $banner = Banner::factory()->create(['is_active' => false, 'starts_at' => null, 'ends_at' => null]);

        $this->assertSame('Inactive', $banner->liveStatusLabel());
    }

    public function test_a_banner_scheduled_for_the_future_says_so_with_the_start_date(): void
    {
        $banner = Banner::factory()->create(['is_active' => true, 'starts_at' => now()->addDays(2)]);

        $this->assertStringStartsWith('Scheduled — starts', $banner->liveStatusLabel());
    }

    public function test_a_banner_past_its_end_date_says_expired(): void
    {
        $banner = Banner::factory()->create(['is_active' => true, 'ends_at' => now()->subDay()]);

        $this->assertStringStartsWith('Expired — ended', $banner->liveStatusLabel());
    }

    public function test_a_banner_within_its_schedule_window_is_live_now(): void
    {
        $banner = Banner::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->assertSame('Live now', $banner->liveStatusLabel());
    }
}

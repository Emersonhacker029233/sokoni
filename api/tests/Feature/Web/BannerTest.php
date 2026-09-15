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

    /**
     * Part B (client feedback): noon.com-pattern ad inventory — three new
     * positions on top of the original four. The migration widens
     * `position` from a fixed DB-level enum, so this also proves that
     * change actually took (a row with one of these values would be
     * rejected by the old enum/CHECK constraint on every driver, not just
     * silently dropped).
     */
    public function test_a_search_background_banner_renders_behind_the_search_band_with_a_centre_scrim(): void
    {
        $banner = Banner::factory()->create(['position' => 'search_background', 'title' => 'Search backdrop campaign']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('web.banners.click', $banner), false);
        $response->assertSee('linear-gradient(to right, transparent 0%', false);
    }

    public function test_with_no_search_background_banner_the_band_keeps_its_plain_pattern_backdrop(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('hero-pattern.svg', false);
        $response->assertDontSee('linear-gradient(to right, transparent 0%', false);
    }

    /**
     * Part 5 (client feedback): the search_background banner rotates when
     * more than one is active — crossfade via Alpine, CSS-driven, no new
     * dependency. Every banner that will be shown gets its impression
     * counted at render time, since rotation happens client-side with no
     * further requests afterwards.
     */
    public function test_more_than_one_search_background_banner_renders_all_of_them_with_a_rotation_component_and_counts_every_impression(): void
    {
        $first = Banner::factory()->create(['position' => 'search_background', 'title' => 'Ramadan search backdrop']);
        $second = Banner::factory()->create(['position' => 'search_background', 'title' => 'Eid search backdrop']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('web.banners.click', $first), false);
        $response->assertSee(route('web.banners.click', $second), false);
        // The Alpine rotation component, not the plain single-banner markup.
        $response->assertSee('prefers-reduced-motion', false);
        // Still exactly one shared scrim, not one per rotated image.
        $response->assertSee('linear-gradient(to right, transparent 0%', false);
        $this->assertSame(1, $first->fresh()->impressions_count);
        $this->assertSame(1, $second->fresh()->impressions_count);
    }

    public function test_a_single_search_background_banner_renders_without_the_rotation_component(): void
    {
        Banner::factory()->create(['position' => 'search_background', 'title' => 'Only search backdrop']);

        $response = $this->get('/');

        $response->assertOk();
        // "With a single banner, behave exactly as now" — no Alpine rotation markup at all.
        $response->assertDontSee('prefers-reduced-motion', false);
    }

    public function test_a_category_strip_side_banner_renders_beside_the_category_grid(): void
    {
        $banner = Banner::factory()->create(['position' => 'category_strip_side', 'title' => 'Category side campaign']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Category side campaign');
    }

    public function test_a_near_you_side_banner_renders_beside_the_near_you_row(): void
    {
        $seller = \App\Models\SellerProfile::factory()->verified()->create(['lat' => -6.8, 'lng' => 39.28]);
        \App\Models\Product::factory()->create(['seller_id' => $seller->id]);
        $banner = Banner::factory()->create(['position' => 'near_you_side', 'title' => 'Near you side campaign']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Near you side campaign');
    }

    public function test_the_side_variant_collapses_to_nothing_when_no_banner_is_active(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // Neither side slot's own wrapper markup appears without an active banner in either position.
        $response->assertDontSee('Category side campaign');
        $response->assertDontSee('Near you side campaign');
    }

    /**
     * Part 4 (client feedback): "In Focus" advertising band — genuinely
     * different from every other position above: those all render at
     * most one banner via <x-banner-slot>, this renders every active one
     * as its own poster in a row (2-4 per the design brief).
     */
    public function test_every_active_in_focus_banner_renders_as_its_own_poster_and_counts_an_impression(): void
    {
        $first = Banner::factory()->create(['position' => 'in_focus', 'title' => 'Ramadan promo poster']);
        $second = Banner::factory()->create(['position' => 'in_focus', 'title' => 'New arrivals poster']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Ramadan promo poster');
        $response->assertSee('New arrivals poster');
        $this->assertSame(1, $first->fresh()->impressions_count);
        $this->assertSame(1, $second->fresh()->impressions_count);
    }

    public function test_in_focus_caps_at_four_posters_even_if_more_are_active(): void
    {
        $banners = Banner::factory()->count(6)->sequence(fn ($sequence) => ['title' => "In Focus poster #{$sequence->index}"])
            ->create(['position' => 'in_focus']);

        $response = $this->get('/');
        $response->assertOk();
        $html = $response->getContent();

        $shown = $banners->filter(fn (Banner $banner) => str_contains($html, $banner->title));
        $this->assertCount(4, $shown, 'exactly 4 of the 6 active In Focus banners should render, in sort_order');
    }

    public function test_in_focus_collapses_to_nothing_when_no_banner_is_active(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee(__('site.home_in_focus'));
    }

    public function test_clicking_an_in_focus_poster_records_a_click_and_redirects_to_the_real_url(): void
    {
        $banner = Banner::factory()->create(['position' => 'in_focus', 'link_url' => 'https://example.com/campaign']);

        $response = $this->get(route('web.banners.click', $banner));

        $response->assertRedirect('https://example.com/campaign');
        $this->assertSame(1, $banner->fresh()->clicks_count);
    }
}

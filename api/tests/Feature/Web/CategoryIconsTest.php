<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against the reported bug: categories.icon (a Material Symbols
 * name meant for the Flutter app's icon font) was being printed as raw
 * visible text on the home page, since the website loads no icon font.
 */
class CategoryIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_real_seeded_category_icon_renders_as_svg_not_raw_text(): void
    {
        $this->seed(CategorySeeder::class);

        $response = $this->get('/');

        $response->assertOk();

        // Only top-level categories carry an icon and render on the home
        // page's category grid — subcategories (mega menu/search sidebar
        // feature) are text-only in the mega menu columns and have no icon
        // column value at all, so they're correctly excluded here.
        foreach (Category::whereNotNull('icon')->pluck('icon') as $iconName) {
            // The raw Material Symbols name must never appear as visible
            // text — it's only ever a lookup key into the SVG map now.
            $response->assertDontSee('>'.$iconName.'<', false);
        }

        // Every seeded icon is mapped — the SVG component should never
        // have had to fall through to the generic bag icon for real data.
        $response->assertSeeInOrder(['<svg', 'viewBox="0 0 24 24"'], false);
    }

    public function test_an_unmapped_icon_name_falls_back_to_the_generic_icon_without_erroring(): void
    {
        Category::factory()->create(['name_en' => 'Something New', 'parent_id' => null, 'is_active' => true, 'icon' => 'totally_unknown_icon_name']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('totally_unknown_icon_name');
    }

    /**
     * Part 3 (client feedback): noon.com-style photo tiles — every seeded
     * category has no `image` set, so every tile on a fresh seed falls
     * back to the icon-on-yellow-tile treatment, at the tile size the
     * photo tiles also use (so a photographed and an unphotographed
     * category sit at the same size in the same row).
     */
    public function test_the_homepage_category_icon_fallback_renders_at_the_photo_tile_size(): void
    {
        $this->seed(CategorySeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('h-80 w-80', false);
        $response->assertSee('h-32 w-32', false);
    }
}

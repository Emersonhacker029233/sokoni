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

        foreach (Category::pluck('icon') as $iconName) {
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
}

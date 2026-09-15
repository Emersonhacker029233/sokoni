<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Part 3 (client feedback): the homepage's "Browse categories" section
 * rebuilt as noon.com-style photo tiles. Complements CategoryIconsTest
 * (the icon-fallback rendering) and MegaMenuTest (the "exactly the nav
 * bar's list" requirement) with the new, specific behaviours: an admin
 * photo actually renders as the tile image, a category with no photo
 * still falls back cleanly, and the whole tile is one link.
 */
class HomeCategoryTilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_with_a_photo_renders_it_as_a_circular_cover_image(): void
    {
        Storage::fake('public');
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $imageUrl = Storage::disk('public')->url('categories/electronics.jpg');
        $electronics->update(['image' => $imageUrl]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('src="'.$imageUrl.'"', false);
        $response->assertSee('rounded-full object-cover', false);
    }

    public function test_a_category_with_no_photo_falls_back_to_its_icon_on_a_yellow_tile(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $this->assertNull($electronics->image);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('bg-sokoni-yellow', false);
    }

    public function test_the_whole_tile_is_a_single_link_to_the_category_page(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();

        $response = $this->get('/');
        $html = $response->getContent();

        // The category name and its link href must both live inside the
        // same <a> element — not an image linked separately from a plain
        // text caption underneath it.
        $this->assertMatchesRegularExpression(
            '#<a\s+href="'.preg_quote(route('web.category', 'electronics'), '#').'"[^>]*>.*?Electronics.*?</a>#s',
            $html
        );
    }

    public function test_the_section_is_horizontally_scrollable_with_no_visible_scrollbar_on_mobile(): void
    {
        (new CategorySeeder)->run();

        $response = $this->get('/');

        $response->assertOk();
        // Same utility class every other mobile-scroll/desktop-grid row on
        // this page already uses (Near you, Offers) — see resources/css/app.css.
        $response->assertSee('no-scrollbar flex flex-1 gap-16 overflow-x-auto', false);
        $response->assertSee('sm:grid sm:grid-cols-4', false);
    }
}

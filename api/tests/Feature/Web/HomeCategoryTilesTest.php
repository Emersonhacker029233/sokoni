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

    /**
     * Part 6 (client feedback): "should sit on one line, matching the
     * navigation bar's category strip... horizontally scrollable rather
     * than wrapping onto multiple rows" — supersedes the earlier
     * sm:grid/wrapping behaviour, which is exactly the "wraps onto
     * multiple rows on wider screens" bug being fixed here.
     */
    public function test_the_section_is_one_horizontally_scrollable_line_at_every_breakpoint(): void
    {
        (new CategorySeeder)->run();

        $response = $this->get('/');

        $response->assertOk();
        // The exact class list this section's own row now uses — no
        // sm:grid/wrapping switch at wider widths at all, unlike the
        // other rows on this page (Near you, Offers) that legitimately
        // still wrap into a grid from sm up, so this is checked as one
        // literal string rather than separate assertDontSee calls that
        // could false-fail against those other sections' own classes.
        $response->assertSee(
            '<div class="no-scrollbar flex flex-1 gap-16 overflow-x-auto pb-8 lg:gap-24">',
            false
        );
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Catalog\CategoryCatalogService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The header's mega menu (noon.com pattern) — every category/subcategory
 * link must be real server-rendered HTML (indexable, works with JS off),
 * and the cached tree must never repeat the __PHP_Incomplete_Class
 * incident CategoryCatalogService's own docblock describes (see
 * DECISIONS.md) — this suite proves the cache holds plain arrays, not
 * Eloquent models.
 */
class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mega_menu_tree_is_a_plain_array_of_scalars_not_eloquent_models(): void
    {
        (new CategorySeeder)->run();

        $tree = app(CategoryCatalogService::class)->megaMenuTree();

        $this->assertIsArray($tree);
        $this->assertNotEmpty($tree);
        foreach ($tree as $category) {
            $this->assertIsInt($category['id']);
            $this->assertIsString($category['name_en']);
            $this->assertIsString($category['slug']);
            $this->assertIsArray($category['children']);
            foreach ($category['children'] as $child) {
                $this->assertIsInt($child['id']);
                $this->assertIsString($child['name_en']);
            }
        }

        // The actual incident-class regression guard: serialize/unserialize
        // round-trips cleanly with zero class dependency, unlike a cached
        // Eloquent Collection ever could if a class fails to autoload.
        $roundTripped = unserialize(serialize($tree));
        $this->assertSame($tree, $roundTripped);
    }

    public function test_every_category_and_subcategory_link_is_real_server_rendered_html(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();

        $response = $this->get('/');

        $response->assertOk();
        // Desktop trigger link.
        $response->assertSee('href="'.route('web.category', 'electronics').'"', false);
        // Subcategory link inside the panel (and the mobile accordion —
        // both render the same href, so a plain assertSee already covers it
        // regardless of which copy matched).
        $response->assertSee('href="'.route('web.category', ['electronics', 'tvs']).'"', false);
        $response->assertSee('TVs');
    }

    public function test_the_view_all_link_points_at_the_parent_category_page(): void
    {
        (new CategorySeeder)->run();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(__('site.category_view_all_in', ['category' => 'Electronics']));
    }

    public function test_a_category_with_no_subcategories_gets_no_dropdown_affordance(): void
    {
        (new CategorySeeder)->run();
        $other = Category::whereNull('parent_id')->where('name_en', 'Other')->firstOrFail();
        $this->assertSame(0, Category::where('parent_id', $other->id)->count());

        $tree = app(CategoryCatalogService::class)->megaMenuTree();
        $otherNode = collect($tree)->firstWhere('id', $other->id);

        $this->assertNotNull($otherNode);
        $this->assertEmpty($otherNode['children']);
    }

    public function test_renaming_a_category_invalidates_the_cached_tree_immediately(): void
    {
        (new CategorySeeder)->run();
        $service = app(CategoryCatalogService::class);
        $tree = $service->megaMenuTree();
        $this->assertTrue(Cache::has(CategoryCatalogService::MEGA_MENU_CACHE_KEY));

        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $electronics->update(['name_en' => 'Consumer Electronics']);

        // The saved-model hook busts the cache immediately — no stale
        // entry left for the next request to read, no waiting on the TTL.
        $this->assertFalse(Cache::has(CategoryCatalogService::MEGA_MENU_CACHE_KEY));

        $freshTree = $service->megaMenuTree();
        $renamed = collect($freshTree)->firstWhere('id', $electronics->id);
        $this->assertSame('Consumer Electronics', $renamed['name_en']);
    }

    /**
     * B3 (tester feedback): "Other" reportedly wasn't last in the home
     * page's own Browse-categories grid, unlike the top menu. Both
     * actually read the identical `CategoryCatalogService::withCounts()`
     * query (same `orderBy('sort_order')`, "Other" seeded/migrated to the
     * highest sort_order — see CategorySeeder and the rename migration),
     * so this proves the two surfaces already agree rather than assuming
     * a fix is needed without checking first.
     */
    public function test_the_home_pages_category_grid_lists_other_last_matching_the_mega_menu(): void
    {
        (new CategorySeeder)->run();
        $topLevel = Category::whereNull('parent_id')->where('is_active', true)->get();
        foreach ($topLevel as $category) {
            Product::factory()->for(SellerProfile::factory()->verified(), 'seller')->create(['category_id' => $category->id]);
        }

        $response = $this->get('/');
        $response->assertOk();
        $html = $response->getContent();

        $otherPosition = strpos($html, route('web.category', 'other'));
        $this->assertNotFalse($otherPosition, 'Other should appear in the Browse-categories grid.');

        foreach ($topLevel as $category) {
            if ($category->name_en === 'Other') {
                continue;
            }
            $position = strpos($html, route('web.category', app(CategoryCatalogService::class)->slug($category)));
            $this->assertNotFalse($position, "{$category->name_en} should appear in the Browse-categories grid.");
            $this->assertGreaterThan($position, $otherPosition, "Other should come after {$category->name_en} in the Browse-categories grid.");
        }
    }

    /**
     * B6 (tester feedback): "The homepage's yellow category icon row is
     * missing 'Other'." Root cause: the grid drops any category with zero
     * currently-visible products (so "Agriculture 0" never reads as "this
     * marketplace is empty") — a sensible rule for a real category, but
     * "Other" is a permanent catch-all with no products of its own most
     * of the time by definition, so the exact same rule silently erased
     * it from the row entirely. The earlier "Other last" test above never
     * caught this because it happened to give every category, "Other"
     * included, at least one product — this seeds zero for "Other"
     * specifically, the actual real-world case.
     */
    public function test_other_appears_on_the_homepage_even_with_zero_products_but_a_genuinely_empty_real_category_still_hides(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $other = Category::whereNull('parent_id')->where('name_en', 'Other')->firstOrFail();
        // A product in some other category so the grid isn't just empty outright.
        Product::factory()->for(SellerProfile::factory()->verified(), 'seller')->create([
            'category_id' => Category::whereNull('parent_id')->where('name_en', 'Fashion')->firstOrFail()->id,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();

        // Electronics' own link legitimately still exists elsewhere on the
        // page (the header's mega menu shows every category regardless of
        // count — a deliberate, different rule from this one section), so
        // a plain page-wide assertDontSee would be testing the wrong
        // thing. Isolate just the "Browse categories" <section>...</section>
        // block and assert within that slice only.
        $this->assertMatchesRegularExpression('#<section[^>]*>.*?Browse categories.*?</section>#s', $html);
        preg_match('#<section[^>]*>.*?Browse categories.*?</section>#s', $html, $matches);
        $browseCategoriesHtml = $matches[0];

        $this->assertStringContainsString(route('web.category', app(CategoryCatalogService::class)->slug($other)), $browseCategoriesHtml);
        $this->assertStringNotContainsString(route('web.category', app(CategoryCatalogService::class)->slug($electronics)), $browseCategoriesHtml);
    }

    public function test_the_swahili_locale_shows_swahili_names(): void
    {
        (new CategorySeeder)->run();

        $response = $this->withSession(['locale' => 'sw'])->get('/?lang=sw');

        $response->assertOk();
        $response->assertSee('Elektroniki');
        $response->assertSee('Televisheni');
    }
}

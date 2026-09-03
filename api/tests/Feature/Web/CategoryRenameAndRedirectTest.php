<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client request (2026-09-03): Agriculture -> Cereal & Legume,
 * Construction & Hardware -> Hardware, Real Estate added third, Kids
 * added, Other always last. Slugs are computed from name_en (not stored),
 * so a rename changes the URL too — old links need a real redirect, not
 * a 404.
 */
class CategoryRenameAndRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_agriculture_slug_redirects_permanently_to_cereal_legume(): void
    {
        (new CategorySeeder)->run();

        $response = $this->get('/c/agriculture');

        $response->assertRedirect('/c/cereal-legume');
        $response->assertStatus(301);
    }

    public function test_the_old_construction_hardware_slug_redirects_permanently_to_hardware(): void
    {
        (new CategorySeeder)->run();

        $response = $this->get('/c/construction-hardware');

        $response->assertRedirect('/c/hardware');
        $response->assertStatus(301);
    }

    public function test_the_old_slug_redirect_preserves_a_child_category_segment(): void
    {
        (new CategorySeeder)->run();
        $hardware = Category::where('name_en', 'Hardware')->firstOrFail();
        Category::create([
            'parent_id' => $hardware->id,
            'name_en' => 'Power Tools',
            'name_sw' => 'Zana za Umeme',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Child slugs are also computed from name_en, not id — the redirect
        // only ever rewrites the parent segment, so the child slug carries
        // through unchanged.
        $this->get('/c/construction-hardware/power-tools')
            ->assertRedirect('/c/hardware/power-tools')
            ->assertStatus(301);
    }

    public function test_the_new_cereal_legume_slug_resolves_directly(): void
    {
        (new CategorySeeder)->run();

        $this->get('/c/cereal-legume')->assertOk();
    }

    public function test_real_estate_is_third_and_other_is_last(): void
    {
        (new CategorySeeder)->run();

        $topLevel = Category::whereNull('parent_id')->orderBy('sort_order')->pluck('name_en')->values();

        $this->assertSame('Real Estate', $topLevel[2]);
        $this->assertSame('Other', $topLevel->last());
    }

    public function test_kids_category_exists_with_the_required_swahili_name(): void
    {
        (new CategorySeeder)->run();

        $this->assertDatabaseHas('categories', ['name_en' => 'Kids', 'name_sw' => 'Watoto']);
    }

    public function test_every_top_level_category_has_both_english_and_swahili_names(): void
    {
        (new CategorySeeder)->run();

        $missing = Category::whereNull('parent_id')
            ->where(fn ($q) => $q->whereNull('name_sw')->orWhere('name_sw', ''))
            ->count();

        $this->assertSame(0, $missing, 'Every top-level category must have a Swahili name.');
    }
}

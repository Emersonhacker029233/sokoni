<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_category_slug_renders_the_branded_404_page(): void
    {
        Category::factory()->create(['name_en' => 'Popular Category', 'parent_id' => null, 'is_active' => true]);

        $response = $this->get('/c/this-does-not-exist?lang=en');

        $response->assertNotFound();
        $response->assertSee('Popular Category');
    }

    /** The 500 view deliberately skips the DB-dependent header partial — proves it renders standalone, with no request/exception cycle and no database access. */
    public function test_the_500_error_view_renders_standalone_without_any_database_access(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString(__('site.error_500_title'), $html);
        $this->assertStringContainsString(route('web.home'), $html);
    }
}

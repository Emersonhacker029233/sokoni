<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Replaces the old plain EN/SW text toggle with a proper dropdown — language only, no currency switcher (see DECISIONS.md). */
class LanguageDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_shows_a_language_dropdown_with_both_locales(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('English');
        $response->assertSee('Kiswahili');
        // The dropdown, not the old side-by-side EN/SW chip pair.
        $response->assertSee('x-data="{ langOpen: false }"', false);
    }

    public function test_no_currency_selector_exists_anywhere_on_the_home_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('USD');
        $response->assertDontSee('currency', false);
    }
}

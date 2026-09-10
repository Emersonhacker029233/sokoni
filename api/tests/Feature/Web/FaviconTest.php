<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B4 (tester feedback): "proper favicon set from the Sokoni logo" —
 * previously `public/favicon.ico` was a 0-byte placeholder (any browser
 * requesting /favicon.ico directly got an empty response), and the layout
 * only ever linked one 1080px source image with no size hints, no
 * apple-touch-icon, and no web manifest at all.
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_links_a_full_favicon_set(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="icon" href="'.asset('favicon.ico').'"', false);
        $response->assertSee('rel="apple-touch-icon" sizes="180x180" href="'.asset('images/brand/apple-touch-icon-180.png').'"', false);
        $response->assertSee('rel="manifest" href="'.asset('site.webmanifest').'"', false);
    }

    public function test_favicon_ico_is_a_real_non_empty_file(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
    }

    public function test_every_generated_icon_has_a_solid_opaque_background(): void
    {
        foreach ([
            public_path('images/brand/favicon-32.png'),
            public_path('images/brand/apple-touch-icon-180.png'),
            public_path('images/brand/icon-512.png'),
        ] as $path) {
            $this->assertFileExists($path);
            $image = imagecreatefrompng($path);
            $corner = imagecolorat($image, 0, 0);
            $alpha = ($corner & 0x7F000000) >> 24;
            // GD's alpha channel is 0 (fully opaque) to 127 (fully
            // transparent) — 0 here is the actual, meaningful assertion.
            $this->assertSame(0, $alpha, "{$path} must have a solid, opaque background.");
        }
    }
}

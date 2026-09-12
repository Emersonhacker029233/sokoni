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

    /**
     * C5 (client feedback): "favicon has a white background" — the
     * previous set was intentionally solid everywhere (this test used to
     * assert exactly that). The source it was generated from had already
     * been flattened onto white with no alpha left to key out; regenerated
     * from the full logo lockup (sokoni_logo.png), which still has real
     * transparency, instead of the pre-flattened icon-only crop.
     * favicon.ico/favicon-32/icon-512 go transparent — the icon-only crop
     * exposed, no square behind it, browser/OS chrome shows through.
     */
    public function test_favicon_and_manifest_icon_have_a_transparent_background(): void
    {
        // favicon.ico isn't checked here — GD has no ICO decoder at all
        // (imagecreatefromstring returns false for it) — but it's built
        // from the exact same transparent composite as favicon-32.png
        // below, and its own non-empty-file check lives above.
        foreach ([
            public_path('images/brand/favicon-32.png'),
            public_path('images/brand/icon-512.png'),
        ] as $path) {
            $this->assertFileExists($path);
            $image = imagecreatefrompng($path);
            $this->assertNotFalse($image, "{$path} must decode as a real PNG.");
            $corner = imagecolorat($image, 0, 0);
            $alpha = ($corner & 0x7F000000) >> 24;
            // GD's alpha channel is 0 (fully opaque) to 127 (fully
            // transparent) — 127 here is the actual, meaningful assertion.
            $this->assertSame(127, $alpha, "{$path} must have a transparent background, not a solid square.");
        }
    }

    /**
     * apple-touch-icon deliberately stays solid, not an oversight — Apple's
     * own guidance is against transparency there (historically rendered
     * as solid black on iOS) — but the brand near-black, not white.
     */
    public function test_the_apple_touch_icon_is_solid_brand_near_black_not_white(): void
    {
        $path = public_path('images/brand/apple-touch-icon-180.png');
        $this->assertFileExists($path);

        $image = imagecreatefrompng($path);
        $corner = imagecolorat($image, 0, 0);
        $alpha = ($corner & 0x7F000000) >> 24;
        $this->assertSame(0, $alpha, 'apple-touch-icon must stay fully opaque.');

        $rgb = imagecolorsforindex($image, $corner);
        $this->assertLessThan(30, $rgb['red']);
        $this->assertLessThan(30, $rgb['green']);
        $this->assertLessThan(30, $rgb['blue']);
    }
}

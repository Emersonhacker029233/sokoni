<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_12_shops_with_the_exact_verification_mix(): void
    {
        Storage::fake('public');

        $this->artisan('demo:seed')->assertExitCode(0);

        $shops = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->get();

        $this->assertCount(12, $shops);
        $this->assertCount(8, $shops->where('status', 'verified'));
        $this->assertCount(3, $shops->where('status', 'pending'));
        $this->assertCount(1, $shops->where('status', 'rejected'));
        $this->assertTrue($shops->where('status', 'rejected')->first()->rejection_reason !== null);
    }

    /**
     * A production run of this exact seeder once inserted a 22-character
     * handle straight past `handle`'s VARCHAR(20) column with a hard SQL
     * error — SellerProfile::HANDLE_PATTERN (the same rule a real seller's
     * signup enforces) already limits it to {3,20}, so this is checking
     * DemoSeeder::SHOPS actually complies with the rule the app itself sets,
     * not just that the regex exists.
     */
    public function test_every_shop_handle_satisfies_the_apps_own_validation_rule(): void
    {
        $handles = DemoSeeder::demoHandles();

        foreach ($handles as $handle) {
            $this->assertMatchesRegularExpression(SellerProfile::HANDLE_PATTERN, $handle, "Handle '{$handle}' fails SellerProfile::HANDLE_PATTERN.");
            $this->assertLessThanOrEqual(20, strlen($handle), "Handle '{$handle}' exceeds the seller_profiles.handle VARCHAR(20) column.");
            $this->assertNotContains($handle, SellerProfile::RESERVED_HANDLES, "Handle '{$handle}' is a reserved handle.");
        }

        $this->assertSame($handles, array_unique($handles), 'DemoSeeder::SHOPS contains a duplicate handle.');
    }

    /**
     * Guards against the actual root cause of the production failure: SQLite
     * — this suite's local/CI driver — does not enforce declared VARCHAR or
     * digit-count column lengths, so an overlong value seeds successfully
     * here and only fails once it reaches the real MySQL schema in
     * production. Checks every seeded value's real length/format against
     * the app's actual column definitions and FormRequest rules directly in
     * PHP, so the assertion holds no matter which database driver runs it.
     */
    public function test_every_seeded_value_fits_the_real_column_limits_and_validation_rules(): void
    {
        Storage::fake('public');
        $this->artisan('demo:seed');

        $sellers = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->get();
        $this->assertCount(12, $sellers);

        foreach ($sellers as $seller) {
            $this->assertLessThanOrEqual(20, strlen($seller->handle), "seller_profiles.handle is VARCHAR(20): '{$seller->handle}'");
            $this->assertLessThanOrEqual(255, strlen($seller->shop_name), "seller_profiles.shop_name is VARCHAR(255): '{$seller->shop_name}'");
            $this->assertLessThanOrEqual(1000, strlen((string) $seller->bio), "bio must satisfy the app's own max:1000 rule: '{$seller->shop_name}'");
            $this->assertMatchesRegularExpression('/^\+255[67]\d{8}$/', (string) $seller->whatsapp, "whatsapp fails the app's own regex: {$seller->whatsapp}");
            $this->assertLessThanOrEqual(255, strlen((string) $seller->address), "seller_profiles.address is VARCHAR(255).");
            $this->assertMatchesRegularExpression('/^\d{20}$/', (string) $seller->nida_number, "nida_number must be exactly 20 digits (the app's digits:20 rule): {$seller->nida_number}");
        }

        $products = Product::whereIn('seller_id', $sellers->pluck('id'))->get();
        $this->assertCount(60, $products);

        foreach ($products as $product) {
            $this->assertLessThanOrEqual(255, strlen($product->title), "products.title is VARCHAR(255): '{$product->title}'");
            $this->assertLessThanOrEqual(5000, strlen((string) $product->description), "description must satisfy the app's own max:5000 rule: '{$product->title}'");
        }
    }

    public function test_it_seeds_60_products_all_assigned_a_real_category(): void
    {
        Storage::fake('public');

        $this->artisan('demo:seed');

        $sellerIds = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->pluck('id');
        $products = Product::whereIn('seller_id', $sellerIds)->get();

        $this->assertCount(60, $products);
        $this->assertSame(0, $products->whereNull('category_id')->count());
    }

    public function test_it_is_safe_to_run_with_no_categories_seeded_yet(): void
    {
        Storage::fake('public');
        // Not 0: the 2026-09-03 category-rename migration itself plants
        // "Real Estate" and "Kids" directly (not through CategorySeeder),
        // by design — a fresh install should have both immediately, the
        // same way it should get every other permanent category. This
        // test's actual point — no *seeder* has run yet — still holds.
        $this->assertSame(2, Category::count());

        $this->artisan('demo:seed')->assertExitCode(0);

        // The 13 categories in CategorySeeder::CATEGORIES — "Hardware" is a
        // permanent part of that list now (2026-09-03 client rename), not
        // created ad hoc by this seeder as "Construction & Hardware" used to be.
        $this->assertSame(13, Category::count());
        $this->assertDatabaseHas('categories', ['name_en' => 'Hardware']);
    }

    public function test_running_it_twice_without_fresh_does_not_duplicate_orders_reviews_or_comments(): void
    {
        Storage::fake('public');

        $this->artisan('demo:seed');
        $sellerIds = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->pluck('id');
        $firstOrders = Order::whereIn('seller_id', $sellerIds)->count();
        $firstReviews = Review::whereIn('seller_id', $sellerIds)->count();

        $this->artisan('demo:seed');
        $secondOrders = Order::whereIn('seller_id', $sellerIds)->count();
        $secondReviews = Review::whereIn('seller_id', $sellerIds)->count();

        $this->assertSame($firstOrders, $secondOrders);
        $this->assertSame($firstReviews, $secondReviews);
        $this->assertGreaterThan(0, $secondOrders);
    }

    public function test_fresh_clears_prior_demo_shops_and_buyers_but_never_a_real_account(): void
    {
        Storage::fake('public');
        $realSeller = User::factory()->create(['phone' => '+255799999999']);
        SellerProfile::factory()->verified()->create(['user_id' => $realSeller->id, 'handle' => 'a_real_shop_owner']);
        $realBuyer = User::factory()->create(['phone' => '+255788888888']);

        $this->artisan('demo:seed');
        $this->assertSame(12, SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->count());

        $this->artisan('demo:seed --fresh')->assertExitCode(0);

        $this->assertSame(12, SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->count());
        $this->assertDatabaseHas('seller_profiles', ['handle' => 'a_real_shop_owner']);
        $this->assertDatabaseHas('users', ['id' => $realSeller->id]);
        $this->assertDatabaseHas('users', ['id' => $realBuyer->id]);
    }

    public function test_a_bilingual_bio_and_description_are_stored_and_the_locale_aware_accessors_pick_the_right_one(): void
    {
        Storage::fake('public');

        $this->artisan('demo:seed');

        $seller = SellerProfile::where('handle', 'kariakoo_mobile')->firstOrFail();
        $this->assertNotEmpty($seller->bio);
        $this->assertNotEmpty($seller->bio_sw);
        $this->assertSame($seller->bio, $seller->localizedBio('en'));
        $this->assertSame($seller->bio_sw, $seller->localizedBio('sw'));

        $product = Product::where('title', 'Tecno Spark 10')->firstOrFail();
        $this->assertNotEmpty($product->description_sw);
        $this->assertSame($product->description_sw, $product->localizedDescription('sw'));
    }

    public function test_every_seeded_shop_gets_a_distinct_generated_logo(): void
    {
        Storage::fake('public');

        $this->artisan('demo:seed');

        $logos = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->pluck('logo');

        $this->assertCount(12, $logos->filter()->unique());
    }

    /**
     * The actual production bug: a media *row* surviving (e.g. a --fresh
     * that only cleared shops/buyers, or a storage move that reset the
     * disk but not the database) previously left its file never rewritten,
     * because the old guard checked "does a ProductMedia row exist"
     * instead of "does the file it points to actually exist". Deletes only
     * the file, keeps the row exactly as-is, and asserts a second run
     * repairs the file without creating a duplicate row.
     */
    public function test_it_regenerates_a_product_image_file_that_is_missing_even_though_its_row_still_exists(): void
    {
        Storage::fake('public');
        $this->artisan('demo:seed');

        $product = Product::where('title', 'iPhone 12 Pro 256GB')->firstOrFail();
        $media = $product->media()->firstOrFail();
        $relativePath = (string) str($media->path)->after(Storage::disk('public')->url(''));

        Storage::disk('public')->assertExists($relativePath);
        Storage::disk('public')->delete($relativePath);
        Storage::disk('public')->assertMissing($relativePath);

        $this->artisan('demo:seed');

        Storage::disk('public')->assertExists($relativePath);
        $this->assertSame(1, $product->media()->count(), 'must not create a duplicate media row for the same product');
    }

    /** Same self-healing guarantee as products, for Update's image file. */
    public function test_it_regenerates_an_update_image_file_that_is_missing_even_though_its_row_still_exists(): void
    {
        Storage::fake('public');
        $this->artisan('demo:seed');

        $seller = SellerProfile::where('handle', 'kariakoo_mobile')->firstOrFail();
        $update = Update::where('seller_id', $seller->id)->firstOrFail();
        // media_path/thumb_path are full public URLs (same convention as
        // ProductMedia and the real Api\UpdateController) — the fake disk's
        // exists()/delete() need the disk-relative path underneath.
        $relativePath = (string) str($update->media_path)->after(Storage::disk('public')->url(''));

        Storage::disk('public')->assertExists($relativePath);
        Storage::disk('public')->delete($relativePath);

        $this->artisan('demo:seed');

        Storage::disk('public')->assertExists($relativePath);
    }

    /**
     * Same self-healing guarantee for Showcase's poster thumbnail — but
     * `video_path` is a known, unrelated, pre-existing gap this fix
     * doesn't close: there is no video-generation capability anywhere in
     * this codebase (no ffmpeg, no video library), so no file has ever
     * been written there, seeded or re-seeded. Asserted explicitly, not
     * left as an unstated assumption.
     */
    public function test_it_regenerates_a_showcase_thumbnail_file_that_is_missing_even_though_its_row_still_exists(): void
    {
        Storage::fake('public');
        $this->artisan('demo:seed');

        $seller = SellerProfile::where('handle', 'kariakoo_mobile')->firstOrFail();
        $showcase = Showcase::where('seller_id', $seller->id)->firstOrFail();
        $thumbRelative = (string) str($showcase->thumb_path)->after(Storage::disk('public')->url(''));
        $videoRelative = (string) str($showcase->video_path)->after(Storage::disk('public')->url(''));

        Storage::disk('public')->assertExists($thumbRelative);
        Storage::disk('public')->delete($thumbRelative);

        $this->artisan('demo:seed');

        Storage::disk('public')->assertExists($thumbRelative);
        Storage::disk('public')->assertMissing($videoRelative);
    }

    /**
     * A second, distinct bug from the SVG-vs-raster one (tester feedback
     * B1): UpdateResource/ShowcaseResource pass media_path/thumb_path/
     * video_path straight through with no URL-prefixing of their own — a
     * bare relative path here was never a loadable URL for any client at
     * all, unlike Product/ProductMedia's already-correct convention.
     */
    public function test_update_and_showcase_media_paths_are_full_urls_not_bare_relative_paths(): void
    {
        Storage::fake('public');
        $this->artisan('demo:seed');

        $seller = SellerProfile::where('handle', 'kariakoo_mobile')->firstOrFail();
        $update = Update::where('seller_id', $seller->id)->firstOrFail();
        $showcase = Showcase::where('seller_id', $seller->id)->first();

        // Confirms the disk's own URL prefix was actually applied — checked
        // against Storage's own configured base rather than a hardcoded
        // "http" scheme, since the faked disk in this test environment
        // legitimately returns a root-relative "/storage/..." prefix, not
        // a full "https://..." one (that only happens against the real,
        // non-faked PUBLIC_UPLOADS_URL-driven disk in production).
        $urlBase = Storage::disk('public')->url('');
        $this->assertStringStartsWith($urlBase, $update->media_path);
        $this->assertStringStartsWith($urlBase, $update->thumb_path);
        if ($showcase) {
            $this->assertStringStartsWith($urlBase, $showcase->thumb_path);
            $this->assertStringStartsWith($urlBase, $showcase->video_path);
        }
    }

    /** Not just "does the seeder run" — does the real website actually render this content end to end. */
    public function test_the_stores_shop_and_product_pages_all_render_the_real_seeded_content(): void
    {
        Storage::fake('public');
        $this->artisan('demo:seed');

        $this->get('/stores')
            ->assertOk()
            ->assertSee('Kariakoo Mobile Center')
            ->assertSee('Sinza Fresh Grocers');

        $this->get('/@kariakoo_mobile')
            ->assertOk()
            ->assertSee('Kariakoo Mobile Center')
            ->assertSee('Kariakoo');

        $product = Product::where('title', 'iPhone 12 Pro 256GB')->firstOrFail();
        $this->get('/p/'.$product->id.'/'.\Illuminate\Support\Str::slug($product->title))
            ->assertOk()
            ->assertSee('iPhone 12 Pro 256GB')
            ->assertSee('TSh')
            ->assertDontSee('Lorem');
    }
}

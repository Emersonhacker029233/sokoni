<?php

namespace Tests\Feature\Web;

use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The website had no way to change a shop logo at all (tester feedback
 * A5) — the API-side upload already existed (see Tests\Feature\Sellers\
 * SellerLogoTest); this is its web-guard equivalent via ShopLogoController.
 */
class ShopLogoTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedSellerOwner(): array
    {
        $user = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'sell',
        ]);
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $user->id]);

        return [$user, $seller];
    }

    public function test_the_settings_page_shows_the_logo_uploader_for_a_seller(): void
    {
        [$user, $seller] = $this->onboardedSellerOwner();

        $response = $this->actingAsWebUser($user)->get(route('web.account.settings'));

        // The upload URL is built client-side from just the numeric seller
        // id (`/account/shop/${sellerId}/logo` inside app.js), so it never
        // appears as a literal string in the rendered HTML — assert on
        // what the page actually emits: the Alpine component receiving
        // the right seller id.
        $response->assertOk();
        $response->assertSee("shopLogoUploader({$seller->id},", false);
    }

    public function test_the_settings_page_has_no_logo_uploader_for_a_plain_buyer(): void
    {
        $buyer = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);

        $response = $this->actingAsWebUser($buyer)->get(route('web.account.settings'));

        $response->assertOk();
        $response->assertDontSee(__('site.account_shop_logo'));
    }

    public function test_the_owner_can_upload_a_shop_logo(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSellerOwner();

        $response = $this->actingAsWebUser($user)->postJson(route('web.account.shop.logo', $seller), [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('logo'));
        $this->assertNotNull($seller->refresh()->logo);
    }

    public function test_a_stranger_cannot_upload_another_sellers_logo(): void
    {
        Storage::fake('public');
        [, $seller] = $this->onboardedSellerOwner();
        $stranger = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);

        $this->actingAsWebUser($stranger)->postJson(route('web.account.shop.logo', $seller), [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ])->assertForbidden();
    }

    /**
     * Bug, found while fixing Part 2 (client feedback): uploading a new
     * logo never deleted the previous file — every change just left an
     * orphaned file on disk forever, matching the exact "upload works,
     * nothing ever gets removed" family of bug this round is about.
     */
    public function test_uploading_a_new_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSellerOwner();

        $this->actingAsWebUser($user)->postJson(route('web.account.shop.logo', $seller), [
            'logo' => UploadedFile::fake()->image('first.jpg'),
        ])->assertOk();
        $firstPath = str($seller->fresh()->logo)->after(Storage::disk('public')->url(''))->toString();
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAsWebUser($user)->postJson(route('web.account.shop.logo', $seller), [
            'logo' => UploadedFile::fake()->image('second.jpg'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
    }

    /**
     * Part 2 (client feedback): "an image can be uploaded but not
     * removed — check ... seller logos." Only "Change" existed before.
     */
    public function test_the_owner_can_remove_their_shop_logo(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSellerOwner();
        $this->actingAsWebUser($user)->postJson(route('web.account.shop.logo', $seller), [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);
        $path = str($seller->fresh()->logo)->after(Storage::disk('public')->url(''))->toString();

        $response = $this->actingAsWebUser($user)->deleteJson(route('web.account.shop.logo.destroy', $seller));

        $response->assertOk();
        $this->assertNull($response->json('logo'));
        $this->assertNull($seller->fresh()->logo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_stranger_cannot_remove_another_sellers_logo(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->onboardedSellerOwner();
        $this->actingAsWebUser($user)->postJson(route('web.account.shop.logo', $seller), [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);
        $stranger = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);

        $this->actingAsWebUser($stranger)->deleteJson(route('web.account.shop.logo.destroy', $seller))
            ->assertForbidden();
        $this->assertNotNull($seller->fresh()->logo);
    }

    public function test_removing_a_logo_that_was_never_set_is_a_harmless_no_op(): void
    {
        [$user, $seller] = $this->onboardedSellerOwner();

        $response = $this->actingAsWebUser($user)->deleteJson(route('web.account.shop.logo.destroy', $seller));

        $response->assertOk();
        $this->assertNull($response->json('logo'));
    }
}

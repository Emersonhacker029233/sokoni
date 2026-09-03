<?php

namespace Tests\Feature\Sellers;

use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * NIDA-only verification (client request): the business licence is an
 * optional extra, never a precondition — see SellerOnboardLicenceRequest.
 */
class SellerLicenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_submit_a_licence_file(): void
    {
        Storage::fake('public');
        $seller = SellerProfile::factory()->create();

        $response = $this->actingAs($seller->user)->patchJson("/api/sellers/{$seller->id}/licence", [
            'licence_file' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        ]);

        $response->assertOk();
        $seller->refresh();
        $this->assertNotNull($seller->licence_file);
        Storage::disk('public')->assertExists($seller->licence_file);
    }

    public function test_the_owner_can_advance_the_wizard_without_a_licence_file(): void
    {
        Storage::fake('public');
        $seller = SellerProfile::factory()->create(['licence_file' => null]);

        $response = $this->actingAs($seller->user)->patchJson("/api/sellers/{$seller->id}/licence", []);

        $response->assertOk();
        $seller->refresh();
        $this->assertNull($seller->licence_file);
    }

    public function test_a_verified_seller_can_still_add_a_licence_later(): void
    {
        Storage::fake('public');
        $seller = SellerProfile::factory()->verified()->create(['licence_file' => null]);

        $response = $this->actingAs($seller->user)->patchJson("/api/sellers/{$seller->id}/licence", [
            'licence_file' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        ]);

        $response->assertOk();
        $this->assertNotNull($seller->refresh()->licence_file);
    }

    public function test_a_non_owner_cannot_submit_a_licence_for_someone_elses_shop(): void
    {
        Storage::fake('public');
        $seller = SellerProfile::factory()->create();
        $other = SellerProfile::factory()->create()->user;

        $this->actingAs($other)->patchJson("/api/sellers/{$seller->id}/licence", [])
            ->assertForbidden();
    }
}

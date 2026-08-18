<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Settings;
use App\Models\User;
use App\Support\Settings as SokoniSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_the_settings_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin/settings')->assertOk();
    }

    public function test_a_staff_user_is_refused_the_settings_page(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAsAdmin($staff)->get('/admin/settings')->assertForbidden();
    }

    public function test_saving_settings_persists_an_override_and_is_read_back_by_the_settings_helper(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertSame(5.0, SokoniSettings::searchRadiusKm()); // config default

        $this->actingAsAdmin($admin);

        Livewire::test(Settings::class)
            ->fillForm([
                'search_radius_km' => 8,
                'max_media_per_product' => 6,
                'offer_max_duration_days' => 5,
                'report_auto_hide_threshold' => 2,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(8.0, SokoniSettings::searchRadiusKm());
        $this->assertSame(6, SokoniSettings::maxMediaPerProduct());
        $this->assertSame(5, SokoniSettings::offerMaxDurationDays());
        $this->assertSame(2, SokoniSettings::reportAutoHideThreshold());
    }

    public function test_the_media_upload_cap_actually_enforces_the_configured_setting(): void
    {
        SokoniSettings::set('max_media_per_product', 2);

        $seller = \App\Models\SellerProfile::factory()->verified()->create();
        $product = \App\Models\Product::factory()->create(['seller_id' => $seller->id]);
        \App\Models\ProductMedia::factory()->count(2)->create(['product_id' => $product->id]);

        $this->actingAs($seller->user)
            ->postJson("/api/products/{$product->id}/media", [
                'type' => 'image',
                'file' => \Illuminate\Http\UploadedFile::fake()->image('third.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_the_report_auto_hide_threshold_actually_uses_the_configured_setting(): void
    {
        SokoniSettings::set('report_auto_hide_threshold', 1);

        $product = \App\Models\Product::factory()->create();
        $report = \App\Models\Report::factory()->create([
            'reportable_type' => \App\Models\Product::class,
            'reportable_id' => $product->id,
        ]);

        $report->forceFill(['status' => 'upheld', 'resolved_by' => User::factory()->admin()->create()->id])->save();

        $this->assertTrue($product->fresh()->is_hidden);
    }
}

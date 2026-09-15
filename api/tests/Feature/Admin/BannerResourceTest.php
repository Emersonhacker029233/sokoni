<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_access_the_banners_list(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($user)->get('/admin/banners')->assertForbidden();
    }

    public function test_an_admin_can_load_the_banners_list(): void
    {
        $admin = User::factory()->admin()->create();
        Banner::factory()->create();

        $this->actingAsAdmin($admin)->get('/admin/banners')->assertOk();
    }

    public function test_an_admin_can_create_a_banner_with_an_uploaded_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'Ramadan sale',
                'image_path' => UploadedFile::fake()->image('banner.jpg'),
                'link_url' => 'https://sokoni.co.tz/search?sponsored=1',
                'position' => 'home_hero',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::where('title', 'Ramadan sale')->firstOrFail();
        // The form dehydrates the upload to a full public-disk URL (whatever that resolves to in this environment), not the bare disk-relative path FileUpload stores by default.
        $this->assertStringStartsWith(Storage::disk('public')->url(''), $banner->image_path);
        $this->assertStringContainsString('/banners/', $banner->image_path);
    }

    /**
     * Part B (client feedback): the three new noon.com-pattern positions —
     * `position` is a real DB-level enum, widened by a migration rather
     * than just a Filament Select option, so this proves the row actually
     * saves rather than the form silently rejecting a value the schema
     * itself would still refuse.
     */
    public function test_an_admin_can_create_a_banner_in_one_of_the_new_ad_positions(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'Search backdrop campaign',
                'image_path' => UploadedFile::fake()->image('banner.jpg'),
                'link_url' => 'https://sokoni.co.tz/search',
                'position' => 'search_background',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::where('title', 'Search backdrop campaign')->firstOrFail();
        $this->assertSame('search_background', $banner->position);
    }

    /** Part 4 (client feedback): "In Focus" advertising band — managed through the same Filament Marketing group as every other position. */
    public function test_an_admin_can_create_a_banner_in_the_in_focus_position(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'In Focus launch poster',
                'image_path' => UploadedFile::fake()->image('poster.jpg'),
                'link_url' => 'https://sokoni.co.tz/search',
                'position' => 'in_focus',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::where('title', 'In Focus launch poster')->firstOrFail();
        $this->assertSame('in_focus', $banner->position);
    }

    public function test_an_admin_can_deactivate_a_banner(): void
    {
        $admin = User::factory()->admin()->create();
        $banner = Banner::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(EditBanner::class, ['record' => $banner->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($banner->fresh()->is_active);
    }
}

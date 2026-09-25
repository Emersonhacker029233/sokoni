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
                'image_paths' => [UploadedFile::fake()->image('banner.jpg')],
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
     * Part 4 (client feedback): "allow selecting several images in one
     * go and creating a banner record for each, rather than repeating
     * the form. Each still gets its own link, schedule and active
     * state, editable afterwards."
     */
    public function test_selecting_several_images_creates_one_banner_per_image_sharing_the_other_fields(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'Eid campaign',
                'image_paths' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                    UploadedFile::fake()->image('three.jpg'),
                ],
                'link_url' => 'https://sokoni.co.tz/search?sponsored=eid',
                'position' => 'search_background',
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banners = Banner::where('title', 'Eid campaign')->orderBy('sort_order')->get();

        $this->assertCount(3, $banners);
        // Every field that isn't the image itself is identical across all three.
        $this->assertTrue($banners->every(fn (Banner $b) => $b->link_url === 'https://sokoni.co.tz/search?sponsored=eid'));
        $this->assertTrue($banners->every(fn (Banner $b) => $b->position === 'search_background'));
        $this->assertTrue($banners->every(fn (Banner $b) => $b->is_active === true));
        // Each image produced its own real, distinct stored file.
        $this->assertCount(3, $banners->pluck('image_path')->unique());
        // Distinct, incrementing sort order (not all three tied at 10) —
        // scopeLive() orders by this column, so a tie would leave their
        // relative rotation order undefined.
        $this->assertSame([10, 11, 12], $banners->pluck('sort_order')->all());
    }

    public function test_selecting_a_single_image_still_creates_exactly_one_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'Single image campaign',
                'image_paths' => [UploadedFile::fake()->image('one.jpg')],
                'link_url' => 'https://sokoni.co.tz/search',
                'position' => 'home_mid',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Banner::where('title', 'Single image campaign')->count());
    }

    public function test_creating_banners_redirects_to_the_list_not_a_single_edit_page(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'Redirect check',
                'image_paths' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
                'link_url' => 'https://sokoni.co.tz/search',
                'position' => 'home_mid',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertRedirect(route('filament.admin.resources.banners.index'));
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
                'image_paths' => [UploadedFile::fake()->image('banner.jpg')],
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
                'image_paths' => [UploadedFile::fake()->image('poster.jpg')],
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

    /**
     * Part 1 (client feedback, urgent): "an uploaded banner never
     * appears... the admin must never show a permanently 'loading'
     * state — a failed upload needs a clear error." Confirms the one
     * half of that this application's own code can actually guarantee:
     * a file Filament's own client-side validation rejects produces a
     * real, visible form error rather than silently hanging. The other
     * half — a raw server-level rejection below Laravel entirely — is
     * what diagnose-banners' upload_max_filesize/post_max_size check and
     * the new .user.ini exist to prevent from ever happening in the
     * first place; that half can't be exercised from a PHPUnit request.
     */
    public function test_an_oversized_upload_produces_a_clear_form_error_not_a_silent_hang(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                'title' => 'Oversized poster',
                // BannerForm::maxSize(2048) is in kilobytes; well over it.
                'image_paths' => [UploadedFile::fake()->image('huge.jpg')->size(3000)],
                'link_url' => 'https://sokoni.co.tz/search',
                'position' => 'home_hero',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_paths']);

        $this->assertDatabaseMissing('banners', ['title' => 'Oversized poster']);
    }
}

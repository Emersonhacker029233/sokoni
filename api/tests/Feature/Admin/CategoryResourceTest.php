<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Part 3 (client feedback): "add an image upload field to the Categories
 * resource so the client can set a photograph per category" — same
 * upload/full-URL-dehydration pattern as BannerResourceTest, since
 * CategoryForm's `image` field is built the same way as Banner's
 * `image_path`.
 */
class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_set_a_photo_on_a_new_category(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateCategory::class)
            ->fillForm([
                'name_en' => 'Electronics',
                'name_sw' => 'Elektroniki',
                'image' => UploadedFile::fake()->image('electronics.jpg'),
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('name_en', 'Electronics')->firstOrFail();
        // Same full-URL convention as Banner.image_path/SellerProfile.logo,
        // not the bare disk-relative path FileUpload stores by default.
        $this->assertStringStartsWith(Storage::disk('public')->url(''), $category->image);
        $this->assertStringContainsString('/categories/', $category->image);
    }

    public function test_a_category_can_be_saved_with_no_photo_at_all(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateCategory::class)
            ->fillForm([
                'name_en' => 'Services',
                'name_sw' => 'Huduma',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('name_en', 'Services')->firstOrFail();
        $this->assertNull($category->image);
    }

    public function test_editing_a_category_without_touching_the_photo_field_keeps_the_existing_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['image' => Storage::disk('public')->url('categories/existing.jpg')]);

        Livewire::actingAs($admin)
            ->test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['sort_order' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('/categories/existing.jpg', $category->fresh()->image);
    }
}

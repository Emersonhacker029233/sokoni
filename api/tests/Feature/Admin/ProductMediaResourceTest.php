<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductMedia\Pages\ListProductMedia;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductMediaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_the_media_list(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        ProductMedia::factory()->count(2)->create(['product_id' => $product->id]);

        $this->actingAsAdmin($admin)->get('/admin/product-media')->assertOk();
    }

    public function test_an_admin_can_delete_a_media_item(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $media = ProductMedia::factory()->create(['product_id' => $product->id]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListProductMedia::class)
            ->callTableAction('delete', $media)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('product_media', ['id' => $media->id]);
    }
}

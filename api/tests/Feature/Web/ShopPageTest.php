<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_page_renders_with_local_business_schema(): void
    {
        $seller = SellerProfile::factory()->verified()->create([
            'shop_name' => 'Amina Electronics',
            'opening_hours' => ['monday' => ['open' => '08:00', 'close' => '18:00'], 'sunday' => null],
        ]);
        Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->get("/@{$seller->handle}");

        $response->assertOk();
        $response->assertSee('Amina Electronics');
        $response->assertSee('"@type":"LocalBusiness"', false);
        $response->assertSee('08:00');
    }

    public function test_an_unverified_seller_shop_page_404s(): void
    {
        $seller = SellerProfile::factory()->create(); // pending, not verified

        $this->get("/@{$seller->handle}")->assertNotFound();
    }

    public function test_the_gallery_tab_renders(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->get("/@{$seller->handle}?tab=gallery")->assertOk();
    }

    public function test_the_reviews_tab_renders(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->get("/@{$seller->handle}?tab=reviews")->assertOk();
    }
}

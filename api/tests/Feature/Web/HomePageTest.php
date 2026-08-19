<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_renders(): void
    {
        Category::factory()->create(['name_en' => 'Electronics']);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sokoni', false);
    }

    public function test_the_home_page_renders_with_no_data_at_all(): void
    {
        $this->get('/')->assertOk();
    }
}

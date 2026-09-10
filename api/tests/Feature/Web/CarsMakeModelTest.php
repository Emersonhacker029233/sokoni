<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Support\Legal;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C3 (tester feedback): "Cars: Make and Model attributes — NOT a third
 * category level." Make/Model live in the generic `product_attributes`
 * table, required only when posting into the Cars subcategory, and
 * filterable on the Cars category page.
 */
class CarsMakeModelTest extends TestCase
{
    use RefreshDatabase;

    private function sellerUser(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['terms_accepted_at' => now(), 'terms_version' => Legal::TERMS_VERSION, 'account_intent' => 'sell']);
        SellerProfile::factory()->verified()->create(['user_id' => $user->id]);

        return $user;
    }

    private function carsCategory(): Category
    {
        (new CategorySeeder)->run();

        $vehicles = Category::whereNull('parent_id')->where('name_en', 'Vehicles & Parts')->firstOrFail();

        return Category::where('parent_id', $vehicles->id)->where('name_en', 'Cars')->firstOrFail();
    }

    public function test_posting_into_cars_requires_make_and_model(): void
    {
        $cars = $this->carsCategory();

        $response = $this->actingAsWebUser($this->sellerUser())->post(route('web.account.shop.products.store'), [
            'category_id' => $cars->id,
            'title' => 'Toyota Corolla 2015',
            'price' => 18000000,
            'stock' => 1,
            'condition' => 'used',
        ]);

        $response->assertSessionHasErrors(['make', 'model']);
        $this->assertDatabaseMissing('products', ['title' => 'Toyota Corolla 2015']);
    }

    public function test_posting_into_cars_with_a_model_that_does_not_belong_to_the_make_is_rejected(): void
    {
        $cars = $this->carsCategory();

        $response = $this->actingAsWebUser($this->sellerUser())->post(route('web.account.shop.products.store'), [
            'category_id' => $cars->id,
            'title' => 'Mismatched car',
            'price' => 10000000,
            'stock' => 1,
            'condition' => 'used',
            'make' => 'Toyota',
            'model' => 'Golf', // a Volkswagen model, not a Toyota one
        ]);

        $response->assertSessionHasErrors(['model']);
    }

    public function test_posting_a_valid_car_stores_make_and_model_as_product_attributes(): void
    {
        $cars = $this->carsCategory();

        $response = $this->actingAsWebUser($this->sellerUser())->post(route('web.account.shop.products.store'), [
            'category_id' => $cars->id,
            'title' => 'Toyota Corolla 2015',
            'price' => 18000000,
            'stock' => 1,
            'condition' => 'used',
            'make' => 'Toyota',
            'model' => 'Corolla',
        ]);

        $response->assertRedirect();
        $product = Product::where('title', 'Toyota Corolla 2015')->firstOrFail();
        $this->assertSame('Toyota', $product->attributeValue('make'));
        $this->assertSame('Corolla', $product->attributeValue('model'));
    }

    public function test_posting_into_a_non_cars_category_never_requires_make_or_model(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();

        $response = $this->actingAsWebUser($this->sellerUser())->post(route('web.account.shop.products.store'), [
            'category_id' => $electronics->id,
            'title' => 'A phone charger',
            'price' => 5000,
            'stock' => 10,
            'condition' => 'new',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['title' => 'A phone charger']);
    }

    public function test_editing_a_cars_product_without_touching_make_or_model_leaves_them_untouched(): void
    {
        $cars = $this->carsCategory();
        $seller = $this->sellerUser();
        $product = Product::factory()->create(['seller_id' => $seller->sellerProfile->id, 'category_id' => $cars->id]);
        $product->productAttributes()->create(['key' => 'make', 'value' => 'Nissan']);
        $product->productAttributes()->create(['key' => 'model', 'value' => 'X-Trail']);

        $response = $this->actingAsWebUser($seller)->put(route('web.account.shop.products.update', $product), [
            'price' => 21000000,
        ]);

        $response->assertRedirect();
        $this->assertSame('Nissan', $product->fresh()->attributeValue('make'));
        $this->assertSame('X-Trail', $product->fresh()->attributeValue('model'));
    }

    public function test_the_cars_category_page_filters_by_make_and_model(): void
    {
        $cars = $this->carsCategory();
        $seller = SellerProfile::factory()->verified()->create();
        $toyota = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $cars->id, 'title' => 'Used Toyota Corolla']);
        $toyota->productAttributes()->createMany([
            ['key' => 'make', 'value' => 'Toyota'],
            ['key' => 'model', 'value' => 'Corolla'],
        ]);
        $nissan = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $cars->id, 'title' => 'Used Nissan X-Trail']);
        $nissan->productAttributes()->createMany([
            ['key' => 'make', 'value' => 'Nissan'],
            ['key' => 'model', 'value' => 'X-Trail'],
        ]);

        $response = $this->get(route('web.category', ['vehicles-parts', 'cars']).'?make=Toyota');

        $response->assertOk();
        $response->assertSee('Used Toyota Corolla');
        $response->assertDontSee('Used Nissan X-Trail');
    }

    /**
     * The API controller (`Api\ProductController`) has its own, separate
     * `syncVehicleAttributes()` — not shared code with the web controller,
     * even though both reuse the same FormRequests — so it needs its own
     * direct coverage rather than trusting the web test above for it too.
     */
    public function test_the_api_endpoint_also_persists_make_and_model_as_product_attributes(): void
    {
        $cars = $this->carsCategory();
        $seller = $this->sellerUser();

        $response = $this->actingAs($seller)->postJson('/api/products', [
            'category_id' => $cars->id,
            'title' => 'API Toyota Hilux',
            'price' => 32000000,
            'stock' => 1,
            'condition' => 'used',
            'make' => 'Toyota',
            'model' => 'Hilux',
        ]);

        $response->assertCreated();
        $product = Product::where('title', 'API Toyota Hilux')->firstOrFail();
        $this->assertSame('Toyota', $product->attributeValue('make'));
        $this->assertSame('Hilux', $product->attributeValue('model'));
        $this->assertSame('Toyota', $response->json('data.attributes.make'));
    }

    public function test_the_api_endpoint_leaves_attributes_untouched_when_the_update_omits_them(): void
    {
        $cars = $this->carsCategory();
        $seller = $this->sellerUser();
        $product = Product::factory()->create(['seller_id' => $seller->sellerProfile->id, 'category_id' => $cars->id]);
        $product->productAttributes()->createMany([
            ['key' => 'make', 'value' => 'Suzuki'],
            ['key' => 'model', 'value' => 'Vitara'],
        ]);

        $response = $this->actingAs($seller)->patchJson("/api/products/{$product->id}", ['price' => 15000000]);

        $response->assertOk();
        $this->assertSame('Suzuki', $product->fresh()->attributeValue('make'));
        $this->assertSame('Vitara', $product->fresh()->attributeValue('model'));
    }

    /**
     * The app calls `GET /api/products` directly (not the website's
     * category page), so its own Cars filter needs coverage on that
     * endpoint specifically — a separate code path from
     * `SearchFilterInput`/`CategoryController` above.
     */
    public function test_the_api_products_index_also_filters_by_make(): void
    {
        $cars = $this->carsCategory();
        $seller = SellerProfile::factory()->verified()->create();
        $toyota = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $cars->id, 'title' => 'API Used Toyota']);
        $toyota->productAttributes()->create(['key' => 'make', 'value' => 'Toyota']);
        $nissan = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $cars->id, 'title' => 'API Used Nissan']);
        $nissan->productAttributes()->create(['key' => 'make', 'value' => 'Nissan']);

        $response = $this->getJson('/api/products?make=Toyota');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('API Used Toyota'));
        $this->assertFalse($titles->contains('API Used Nissan'));
    }

    public function test_the_cars_category_page_shows_vehicle_filters_but_other_category_pages_do_not(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();

        $carsResponse = $this->get(route('web.category', ['vehicles-parts', 'cars']));
        $electronicsResponse = $this->get(route('web.category', $electronics->name_en === 'Electronics' ? 'electronics' : ''));

        $carsResponse->assertOk();
        $carsResponse->assertSee('name="make"', false);

        $electronicsResponse->assertOk();
        $electronicsResponse->assertDontSee('name="make"', false);
    }
}

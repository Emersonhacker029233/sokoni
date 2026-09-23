<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug (client feedback): "Tapping almost anything in the app throws:
 * Unexpected error: type 'List<dynamic>?' is not a subtype of type
 * 'Map<String, dynamic>?'" — the app force-closes to recover.
 *
 * Root cause: three endpoints built a JSON-object-shaped field from an
 * empty PHP array/Collection (`->pluck(...)`, an `array`-cast Eloquent
 * attribute defaulting to `[]`). `json_encode()` has no way to tell an
 * empty list from an empty map, so it always emits `[]` for an empty
 * one — but the Flutter client casts that field `as Map<String,
 * dynamic>?`, which throws on a `[]`. This hit almost every screen
 * because it's the common case (most products have no Cars-only
 * attributes; a new seller has no reviews yet) — the rare, attribute-
 * bearing case always worked fine, which is exactly why it shipped
 * unnoticed. Every test below is a real client-facing JSON shape
 * contract: assert the field is a JSON *object* (`{}`), never a bare
 * array, regardless of whether it's empty.
 */
class ResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private function sellerUser(): User
    {
        $seller = SellerProfile::factory()->verified()->create();

        return $seller->user;
    }

    public function test_a_product_with_no_attributes_serializes_attributes_as_an_empty_object_not_a_list(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();

        // assertJson's loose array/object matching would accept either
        // shape here — the raw response body is what the client actually
        // parses, so assert against that directly instead.
        $this->assertStringContainsString('"attributes":{}', $response->getContent());
    }

    public function test_a_cars_product_still_serializes_its_real_attributes_correctly(): void
    {
        $carsCategory = Category::factory()->create(['name_en' => 'Cars']);
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $carsCategory->id]);
        $product->productAttributes()->createMany([
            ['key' => 'make', 'value' => 'Toyota'],
            ['key' => 'model', 'value' => 'Corolla'],
        ]);

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();

        $response->assertJsonPath('data.attributes.make', 'Toyota');
        $response->assertJsonPath('data.attributes.model', 'Corolla');
    }

    public function test_creating_a_product_returns_an_empty_object_not_a_list_for_attributes(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->sellerUser())->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'A phone',
            'price' => 100000,
            'stock' => 1,
            'condition' => 'new',
        ])->assertCreated();

        $this->assertStringContainsString('"attributes":{}', $response->getContent());
    }

    public function test_updating_a_product_also_returns_an_empty_object_not_a_list_for_attributes(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAs($seller->user)->patchJson("/api/products/{$product->id}", [
            'category_id' => $product->category_id,
            'title' => 'Updated title',
            'price' => $product->price,
            'stock' => $product->stock,
            'condition' => $product->condition,
        ])->assertOk();

        $this->assertStringContainsString('"attributes":{}', $response->getContent());
    }

    public function test_a_seller_with_no_reviews_yet_gets_an_empty_object_not_a_list_for_the_rating_distribution(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $response = $this->getJson("/api/sellers/{$seller->handle}/reviews")->assertOk();

        $this->assertStringContainsString('"distribution":{}', $response->getContent());
    }

    public function test_a_notification_with_no_extra_payload_gets_an_empty_object_not_a_list(): void
    {
        // Matches an admin moderation action exactly — e.g.
        // Filament\Resources\Users\Tables\UsersTable's "Warning"/"Suspend"/
        // "Ban" row actions all call PushNotifier::notify() with only 3
        // arguments, relying on its $data = [] default.
        $user = User::factory()->create();
        $user->appNotifications()->create([
            'title' => 'Warning from Sokoni',
            'body' => 'Please review our terms.',
            'data' => [],
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertStringContainsString('"payload":{}', $response->getContent());
    }

    public function test_a_notification_with_a_real_payload_still_serializes_it_correctly(): void
    {
        $user = User::factory()->create();
        $user->appNotifications()->create([
            'title' => 'New message',
            'body' => 'Someone messaged you.',
            'data' => ['conversation_id' => 42],
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $response->assertJsonPath('data.0.payload.conversation_id', 42);
    }
}

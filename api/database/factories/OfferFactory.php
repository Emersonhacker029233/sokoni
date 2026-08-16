<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'seller_id' => $product->seller_id,
            'product_id' => $product->id,
            'discount_type' => 'percent',
            'discount_value' => fake()->numberBetween(10, 50),
            'price_snapshot' => $product->price,
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDay(),
        ]);
    }
}

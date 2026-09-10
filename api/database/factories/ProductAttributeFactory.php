<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'key' => 'make',
            'value' => fake()->randomElement(['Toyota', 'Nissan', 'Honda']),
        ];
    }
}

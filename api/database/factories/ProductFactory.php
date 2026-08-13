<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => SellerProfile::factory(),
            'category_id' => Category::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->paragraph(3),
            'price' => fake()->numberBetween(3, 400) * 5000,
            'currency' => 'TZS',
            'stock' => fake()->numberBetween(0, 50),
            'condition' => fake()->randomElement(['new', 'used']),
            'is_active' => true,
            'is_hidden' => false,
            'views' => fake()->numberBetween(0, 500),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_hidden' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'title_snapshot' => fake()->words(3, true),
            'price_snapshot' => fake()->numberBetween(3, 400) * 5000,
            'qty' => fake()->numberBetween(1, 3),
        ];
    }
}

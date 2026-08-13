<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->completed(),
            'seller_id' => fn (array $attrs) => Order::find($attrs['order_id'])?->seller_id,
            'buyer_id' => fn (array $attrs) => Order::find($attrs['order_id'])?->buyer_id,
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->boolean(70) ? fake()->sentence(15) : null,
        ];
    }

    public function withReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'reply' => fake()->sentence(10),
            'replied_at' => now(),
        ]);
    }
}

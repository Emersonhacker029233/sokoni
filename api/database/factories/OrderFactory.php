<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5, 100) * 5000;
        $deliveryMethod = fake()->randomElement(['pickup', 'delivery']);
        $deliveryFee = $deliveryMethod === 'delivery' ? fake()->numberBetween(2, 10) * 1000 : 0;

        return [
            'buyer_id' => User::factory(),
            'seller_id' => SellerProfile::factory(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $subtotal + $deliveryFee,
            'delivery_method' => $deliveryMethod,
            'address' => $deliveryMethod === 'delivery' ? fake()->streetAddress() : null,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
            'payment_method' => $deliveryMethod === 'pickup' ? 'pay_on_pickup' : 'cash_on_delivery',
            'payment_status' => 'unpaid',
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'accepted_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    public function ready(): static
    {
        return $this->accepted()->state(fn (array $attributes) => [
            'status' => 'ready',
            'ready_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    public function completed(): static
    {
        return $this->ready()->state(fn (array $attributes) => [
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now()->subHours(fake()->numberBetween(1, 12)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'cancelled_reason' => fake()->randomElement([
                'Out of stock', 'Buyer changed their mind', 'Could not reach buyer',
            ]),
        ]);
    }
}

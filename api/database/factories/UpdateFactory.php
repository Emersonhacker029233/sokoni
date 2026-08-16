<?php

namespace Database\Factories;

use App\Models\SellerProfile;
use App\Models\Update;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Update>
 */
class UpdateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => SellerProfile::factory()->verified(),
            'product_id' => null,
            'type' => 'image',
            'media_path' => 'seed/updates/'.fake()->uuid().'.jpg',
            'thumb_path' => 'seed/updates/'.fake()->uuid().'_thumb.jpg',
            'caption' => fake()->boolean(70) ? fake()->sentence(8) : null,
            'expires_at' => now()->addDay(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subHour()]);
    }
}

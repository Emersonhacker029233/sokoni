<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Showcase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Showcase>
 */
class ShowcaseFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'seller_id' => $product->seller_id,
            'product_id' => $product->id,
            'video_path' => 'seed/showcases/'.fake()->uuid().'.mp4',
            'thumb_path' => 'seed/showcases/'.fake()->uuid().'_thumb.jpg',
            'caption' => fake()->boolean(70) ? fake()->sentence(8) : null,
            'duration' => fake()->numberBetween(5, 60),
            'views' => fake()->numberBetween(0, 5000),
        ];
    }
}

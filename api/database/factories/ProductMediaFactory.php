<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 *
 * Uses picsum.photos (seeded per-product for consistency) and a public
 * domain sample clip for video, so seeded products render real, loadable
 * media in the app during development instead of broken image icons.
 */
class ProductMediaFactory extends Factory
{
    public function definition(): array
    {
        $seed = fake()->numberBetween(1, 9999);

        return [
            'product_id' => Product::factory(),
            'type' => 'image',
            'path' => "https://picsum.photos/seed/{$seed}/1600/1600",
            'thumb_path' => "https://picsum.photos/seed/{$seed}/300/300",
            'duration' => null,
            'sort' => 0,
        ];
    }

    public function video(): static
    {
        $seed = fake()->numberBetween(1, 9999);

        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'path' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'thumb_path' => "https://picsum.photos/seed/{$seed}/800/800",
            'duration' => fake()->numberBetween(8, 60),
        ]);
    }
}

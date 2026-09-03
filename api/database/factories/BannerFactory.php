<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'image_path' => 'https://picsum.photos/seed/'.fake()->uuid().'/1200/300',
            'link_url' => fake()->url(),
            'position' => fake()->randomElement(['home_hero', 'home_mid', 'category_top', 'sidebar']),
            'sort_order' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

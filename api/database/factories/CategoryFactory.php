<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'parent_id' => null,
            'name_en' => ucfirst($name),
            'name_sw' => ucfirst($name),
            'icon' => 'category',
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}

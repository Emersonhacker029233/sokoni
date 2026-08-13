<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => Product::class,
            'reportable_id' => Product::factory(),
            'reason' => fake()->randomElement([
                'Not business content', 'Prohibited item', 'Misleading listing', 'Spam',
            ]),
            'note' => fake()->boolean(40) ? fake()->sentence() : null,
            'status' => 'pending',
        ];
    }
}

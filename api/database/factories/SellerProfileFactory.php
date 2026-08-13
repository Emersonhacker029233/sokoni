<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerProfile>
 */
class SellerProfileFactory extends Factory
{
    /** Dar es Salaam districts, used for both `district` and to spread `lat`/`lng`. */
    private const DISTRICTS = [
        'Kinondoni' => [-6.7735, 39.2695],
        'Ilala' => [-6.8161, 39.2803],
        'Temeke' => [-6.8735, 39.2695],
        'Ubungo' => [-6.7735, 39.2200],
        'Kigamboni' => [-6.8300, 39.3100],
    ];

    public function definition(): array
    {
        [$district, [$baseLat, $baseLng]] = fake()->randomElement(
            array_map(fn ($d, $c) => [$d, $c], array_keys(self::DISTRICTS), self::DISTRICTS)
        );

        return [
            'user_id' => User::factory(),
            'shop_name' => fake()->company(),
            'handle' => fake()->unique()->regexify('[a-z][a-z0-9_]{4,14}'),
            'bio' => fake()->sentence(12),
            'category_id' => Category::factory(),
            'whatsapp' => '+255'.fake()->numerify('7########'),
            'lat' => $baseLat + fake()->randomFloat(6, -0.05, 0.05),
            'lng' => $baseLng + fake()->randomFloat(6, -0.05, 0.05),
            'address' => fake()->streetAddress(),
            'region' => 'Dar es Salaam',
            'district' => $district,
            'nida_number' => fake()->numerify('####################'),
            'nida_image' => 'seed/nida/'.fake()->uuid().'.jpg',
            'licence_file' => 'seed/licences/'.fake()->uuid().'.pdf',
            'status' => 'pending',
            'show_whatsapp' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => fake()->randomElement([
                'ID photo unreadable', 'NIDA number does not match name', 'Licence document expired',
            ]),
        ]);
    }
}

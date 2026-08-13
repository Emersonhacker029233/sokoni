<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+255'.fake()->unique()->numerify('7########'),
            'password' => null,
            'provider' => fake()->randomElement(['google', 'phone']),
            'provider_id' => fake()->uuid(),
            'locale' => fake()->randomElement(['en', 'sw']),
            'terms_accepted_at' => now(),
            'terms_version' => '1.0',
        ];
    }

    /** A buyer/seller who has not yet accepted Terms & Privacy. */
    public function termsNotAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'terms_accepted_at' => null,
            'terms_version' => null,
        ]);
    }

    /** A platform admin, able to sign into the Filament panel with a password. */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }
}

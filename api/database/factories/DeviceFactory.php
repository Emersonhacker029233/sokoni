<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fcm_token' => fake()->uuid(),
            'platform' => fake()->randomElement(['android', 'ios']),
            'last_seen_at' => now(),
        ];
    }
}

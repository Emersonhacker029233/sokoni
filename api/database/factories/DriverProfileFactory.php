<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => ['phone' => '+2557'.fake()->unique()->numerify('########')]),
            'national_id_number' => fake()->numerify('####################'),
            'licence_number' => strtoupper(fake()->bothify('DL#######')),
            'licence_expires_on' => now()->addYears(2)->toDateString(),
            'terms_accepted_at' => now(),
            'terms_version' => '2026-10',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DriverProfile $driver) {
            $driver->forceFill([
                'photo' => $driver->photo ?? 'drivers/test/photo.jpg',
                'national_id_image' => $driver->national_id_image ?? 'drivers/test/nid.jpg',
                'licence_image' => $driver->licence_image ?? 'drivers/test/licence.jpg',
            ]);
        });
    }

    /** Approved, with a verified boda boda, online at the given point (Kariakoo by default). */
    public function approvedOnline(float $lat = -6.8190, float $lng = 39.2760): static
    {
        return $this->state(fn () => [])->afterMaking(function (DriverProfile $driver) use ($lat, $lng) {
            $driver->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'submitted_at' => now(),
                'is_online' => true,
                'last_lat' => $lat,
                'last_lng' => $lng,
                'last_located_at' => now(),
            ]);
        })->afterCreating(function (DriverProfile $driver) {
            $vehicle = Vehicle::query()->create([
                'driver_profile_id' => $driver->id,
                'type' => 'boda_boda',
                'plate_number' => 'MC'.fake()->unique()->numerify('###').strtoupper(fake()->lexify('???')),
                'make' => 'TVS',
                'colour' => 'Red',
                'is_active' => true,
            ]);
            $vehicle->forceFill(['verified_at' => now()])->save();
        });
    }
}

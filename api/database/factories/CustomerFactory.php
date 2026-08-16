<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'seller_id' => SellerProfile::factory()->verified(),
        ];
    }
}

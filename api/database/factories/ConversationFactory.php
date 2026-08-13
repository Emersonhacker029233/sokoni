<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'seller_id' => SellerProfile::factory(),
            'product_id' => null,
            'order_id' => null,
            'last_message_at' => now(),
        ];
    }
}

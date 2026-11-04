<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of Settings → Blocked accounts. `seller_id` lets the app hide
 * the blocked person's shop content too, not just what they posted as a
 * buyer. Deliberately carries no phone, email or username.
 *
 * @mixin \App\Models\UserBlock
 */
class BlockedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $blocked = $this->blocked;
        $shop = $blocked?->sellerProfile;

        return [
            'user_id' => $this->blocked_id,
            'name' => $blocked?->name ?? '',
            'avatar' => $blocked?->avatar,
            'seller_id' => $shop?->id,
            'shop_name' => $shop?->shop_name,
            'handle' => $shop?->handle,
            'logo' => $shop?->logo,
            'blocked_at' => $this->created_at,
        ];
    }
}

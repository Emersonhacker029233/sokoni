<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact seller embed for product listings — just enough for a card
 * (name, handle, rating, verified tick) without the full shop profile.
 *
 * @mixin \App\Models\SellerProfile
 */
class SellerSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_name' => $this->shop_name,
            'handle' => $this->handle,
            'is_verified' => $this->isVerified(),
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
        ];
    }
}

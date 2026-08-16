<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Offer */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'price_snapshot' => (float) $this->price_snapshot,
            'discounted_price' => $this->discountedPrice(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_active' => $this->isActive(),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'product' => new ProductSummaryResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
        ];
    }
}

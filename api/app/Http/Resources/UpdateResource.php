<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Update */
class UpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'media_path' => $this->media_path,
            'thumb_path' => $this->thumb_path,
            'caption' => $this->caption,
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'product' => $this->when(
                $this->relationLoaded('product') && $this->product !== null,
                fn () => new ProductSummaryResource($this->product->loadMissing('media'))
            ),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}

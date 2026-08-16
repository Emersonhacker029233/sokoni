<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact product embed for Offer/Showcase/Update — same "compact embed vs
 * full resource" split as SellerSummaryResource vs SellerProfileResource,
 * so a Showcase feed of many items doesn't repeat each product's full
 * media/category/seller payload.
 *
 * @mixin \App\Models\Product
 */
class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'currency' => $this->currency,
            'cover_image_url' => $this->whenLoaded('media', function () {
                $firstImage = $this->media->firstWhere('type', 'image');
                $item = $firstImage ?? $this->media->first();

                return $item?->thumb_path ?? $item?->path;
            }),
        ];
    }
}

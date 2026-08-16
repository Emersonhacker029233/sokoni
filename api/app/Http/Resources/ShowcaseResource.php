<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Showcase */
class ShowcaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'video_path' => $this->video_path,
            'thumb_path' => $this->thumb_path,
            'caption' => $this->caption,
            'duration' => $this->duration,
            'views' => $this->views,
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'product' => new ProductSummaryResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
        ];
    }
}

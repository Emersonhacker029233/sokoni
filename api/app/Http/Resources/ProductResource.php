<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'stock' => $this->stock,
            'condition' => $this->condition,
            'views' => $this->views,
            // Present only when the query attached a computed distance
            // (see ProductController::index / DistanceQuery).
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 1)
            ),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
            'is_favorited' => $this->when(
                $request->user() !== null,
                fn () => $request->user()->favorites()->where('product_id', $this->id)->exists()
            ),
            'created_at' => $this->created_at,
        ];
    }
}

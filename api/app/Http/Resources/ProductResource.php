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
            'is_active' => $this->is_active,
            'is_hidden' => $this->is_hidden,
            'is_sponsored' => $this->is_sponsored && $this->sponsored_until?->isFuture(),
            'sponsor_contact_method' => $this->when(
                $this->is_sponsored && $this->sponsored_until?->isFuture(),
                $this->sponsor_contact_method
            ),
            'comments_count' => $this->whenCounted('comments'),
            // Present only when the query attached a computed distance
            // (see ProductController::index / DistanceQuery).
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 1)
            ),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
            // C3 (tester feedback): Cars' make/model, exposed generically
            // as a flat key=>value map so Real Estate's own attributes
            // (bedrooms, size, ...) need no resource change to reuse this
            // later — just more rows in the same table.
            'attributes' => $this->when(
                $this->relationLoaded('productAttributes'),
                fn () => $this->productAttributes->pluck('value', 'key')
            ),
            'is_favorited' => $this->when(
                $request->user() !== null,
                fn () => $request->user()->favorites()->where('product_id', $this->id)->exists()
            ),
            'created_at' => $this->created_at,
        ];
    }
}

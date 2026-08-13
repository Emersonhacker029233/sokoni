<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'delivery_method' => $this->delivery_method,
            'address' => $this->address,
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'cancelled_reason' => $this->cancelled_reason,
            'buyer' => new UserResource($this->whenLoaded('buyer')),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'timeline' => $this->timeline(),
            'has_review' => $this->when($this->relationLoaded('review'), fn () => $this->review !== null),
            'created_at' => $this->created_at,
        ];
    }
}

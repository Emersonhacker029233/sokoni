<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'title' => $this->title_snapshot,
            'price' => $this->price_snapshot,
            'qty' => $this->qty,
            'line_total' => $this->lineTotal(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reply' => $this->reply,
            'replied_at' => $this->replied_at,
            'buyer' => new UserResource($this->whenLoaded('buyer')),
            'created_at' => $this->created_at,
        ];
    }
}

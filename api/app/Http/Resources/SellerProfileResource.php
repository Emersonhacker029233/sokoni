<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SellerProfile */
class SellerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_name' => $this->shop_name,
            'handle' => $this->handle,
            'bio' => $this->bio,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'whatsapp' => $this->when($this->show_whatsapp, $this->whatsapp),
            'lat' => $this->when($this->hasLocation(), fn () => (float) $this->lat),
            'lng' => $this->when($this->hasLocation(), fn () => (float) $this->lng),
            'address' => $this->address,
            'region' => $this->region,
            'district' => $this->district,
            'status' => $this->status,
            'rejection_reason' => $this->when(
                $request->user()?->id === $this->user_id,
                $this->rejection_reason
            ),
            'verified_at' => $this->verified_at,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'is_owner' => $this->when(
                $request->user() !== null,
                fn () => $request->user()->id === $this->user_id
            ),
            'created_at' => $this->created_at,
        ];
    }
}

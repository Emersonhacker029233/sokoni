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
            'logo' => $this->logo,
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
            'customer_count' => $this->customer_count,
            // "Listings" count (CLAUDE.md Part 4's three-counts row) — the
            // same visible-only count every viewer's own Listings tab
            // shows, owner included; matching each viewer's own pending/
            // hidden items into this number too would need a
            // viewer-aware query here, not worth the complexity this
            // build's time budget allows for a header stat.
            'products_count' => $this->products()->visible()->count(),
            // How many shops *this seller* (as a buyer) follows — CLAUDE.md
            // Part 4's third header count, distinct from `customer_count`
            // (shops that follow *this* seller).
            'following_count' => $this->user->following()->count(),
            'is_owner' => $this->when(
                $request->user() !== null,
                fn () => $request->user()->id === $this->user_id
            ),
            'is_following' => $this->when(
                $request->user() !== null,
                fn () => $request->user()->following()->where('seller_profiles.id', $this->id)->exists()
            ),
            'created_at' => $this->created_at,
        ];
    }
}

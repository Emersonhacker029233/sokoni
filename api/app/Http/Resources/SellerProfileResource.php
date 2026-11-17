<?php

namespace App\Http\Resources;

use App\Services\Moderation\BlockList;
use App\Support\DeliveryAccess;
use App\Support\OpeningHours;
use App\Support\ReturnPolicy;
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
            // D3 (tester feedback): the website's shop page already shows
            // this (server-rendered Blade reading $seller->opening_hours
            // directly); the app's own shop profile never had it at all
            // because this JSON resource simply never sent it — not a
            // frontend rendering gap, a missing field. Parsed into the
            // full Monday-Sunday shape (never null) so the app never has
            // to special-case a partially-filled `opening_hours` column.
            'opening_hours' => OpeningHours::parse($this->opening_hours),
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
            // Returns (docs/delivery/RETURNS.md): the shop's policy on the
            // shop page. Only for viewers inside the returns launch; no one
            // else gets the key at all.
            'return_policy' => $this->when(
                DeliveryAccess::returnsEnabledFor(BlockList::viewer($request)),
                fn () => ReturnPolicy::toArray($this->return_policy, $this->return_notes)
            ),
            'created_at' => $this->created_at,
        ];
    }
}

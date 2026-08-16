<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact seller embed for product listings — just enough for a card
 * (name, handle, rating, verified tick) plus real coordinates so the
 * discovery map view can place pins at actual shop locations rather than
 * approximating, without the full shop profile.
 *
 * @mixin \App\Models\SellerProfile
 */
class SellerSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_name' => $this->shop_name,
            'logo' => $this->logo,
            'handle' => $this->handle,
            'is_verified' => $this->isVerified(),
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'lat' => $this->when($this->hasLocation(), fn () => (float) $this->lat),
            'lng' => $this->when($this->hasLocation(), fn () => (float) $this->lng),
            // WhatsApp deep link on product pages (CLAUDE.md feature 5),
            // toggleable by the seller.
            'whatsapp' => $this->when($this->show_whatsapp, $this->whatsapp),
            // Lets the "For You" feed card show a Follow button per shop
            // without a second round-trip — same per-row-query trade-off
            // `is_favorited` already accepts on ProductResource.
            'is_following' => $this->when(
                $request->user() !== null,
                fn () => $this->followers()->where('users.id', $request->user()->id)->exists()
            ),
        ];
    }
}

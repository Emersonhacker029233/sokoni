<?php

namespace App\Http\Resources;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Showcase;
use App\Services\Feed\FeedItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tagged union over the feed's three card types, so the client can
 * switch on `type` without guessing a shape from which fields are
 * present. `data` is one of [[ProductResource]], [[OfferResource]] or
 * [[ShowcaseResource]] depending on `type`.
 *
 * @mixin FeedItem
 */
class FeedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'data' => match (true) {
                $this->data instanceof Product => new ProductResource($this->data),
                $this->data instanceof Offer => new OfferResource($this->data),
                $this->data instanceof Showcase => new ShowcaseResource($this->data),
            },
        ];
    }
}

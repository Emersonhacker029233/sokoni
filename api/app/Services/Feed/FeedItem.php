<?php

namespace App\Services\Feed;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Showcase;

/** One typed entry in the composed "For You" feed — see [[FeedService]]. */
final class FeedItem
{
    public function __construct(
        public readonly string $type,
        public readonly Product|Offer|Showcase $data,
    ) {}
}

<?php

namespace App\Services\Feed;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Showcase;
use App\Models\User;
use App\Services\Geo\DistanceQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds the "For You" home feed (CLAUDE.md Part 3): "mix, don't segregate
 * — followed shops first, then nearby verified sellers, then Offers
 * running now, then Showcase videos inline every 6-8 items."
 *
 * The algorithm in two steps:
 *  1. Build an ordered "spine" of typed items in exactly that priority —
 *     followed sellers' Listings, then everything else visible (nearest
 *     first when the buyer's location is known, newest first otherwise —
 *     the same fallback the discovery grid already uses), then Offers
 *     currently running. Sponsored Listings are pulled to the front of
 *     the "everything else" tier (but never ahead of a followed shop —
 *     a boost buys visibility among strangers, not priority over shops
 *     the buyer already chose to follow).
 *  2. Weave one Showcase into the spine after every 6 items (CLAUDE.md
 *     says "every 6-8" — 6 is the tightest legal spacing, so used as the
 *     fixed interval for a stable, testable result), cycling through the
 *     available Showcases if there are more spine pages than Showcases.
 *
 * The result is then paginated 10 items per page. Deliberately
 * recomputed on every call rather than cached — Sokoni's catalogue size
 * (a single-city seller base, per the existing DistanceQuery docblock)
 * makes this cheap, and a stable, request-time-consistent feed matters
 * more here than shaving a few queries.
 */
class FeedService
{
    private const PER_PAGE = 10;

    private const SHOWCASE_INTERVAL = 6;

    public function paginate(?User $viewer, ?float $lat, ?float $lng, int $page): LengthAwarePaginator
    {
        $spine = $this->buildSpine($viewer, $lat, $lng);
        $woven = $this->weaveShowcases($spine);

        $offset = ($page - 1) * self::PER_PAGE;
        $slice = $woven->slice($offset, self::PER_PAGE)->values();

        return new LengthAwarePaginator($slice, $woven->count(), self::PER_PAGE, $page);
    }

    /** @return Collection<int, FeedItem> */
    private function buildSpine(?User $viewer, ?float $lat, ?float $lng): Collection
    {
        $followedSellerIds = $viewer
            ? $viewer->following()->pluck('seller_profiles.id')->all()
            : [];

        $followed = Product::visible()
            ->with(['category', 'seller', 'media'])
            ->whereIn('seller_id', $followedSellerIds)
            ->orderByDesc('created_at')
            ->get();

        $restQuery = Product::visible()
            ->with(['category', 'seller', 'media'])
            ->whereNotIn('seller_id', $followedSellerIds);

        $rest = ($lat !== null && $lng !== null)
            ? $this->orderByDistance($restQuery, $lat, $lng)
            : $restQuery->orderByDesc('created_at')->get();

        // Sponsored Listings float to the front of the non-followed tier —
        // a boost buys visibility among strangers, not priority over shops
        // the buyer already follows.
        $rest = $rest->sortByDesc(fn (Product $p) => $p->is_sponsored && $p->sponsored_until?->isFuture() ? 1 : 0)
            ->values();

        $offers = Offer::visible()
            ->with(['seller', 'product.media'])
            ->orderByDesc('ends_at')
            ->get();

        return $followed->map(fn (Product $p) => new FeedItem('product', $p))
            ->concat($rest->map(fn (Product $p) => new FeedItem('product', $p)))
            ->concat($offers->map(fn (Offer $o) => new FeedItem('offer', $o)))
            ->values();
    }

    /** @return Collection<int, FeedItem> */
    private function weaveShowcases(Collection $spine): Collection
    {
        $showcases = Showcase::visible()->with(['seller', 'product'])->orderByDesc('created_at')->get();

        if ($showcases->isEmpty()) {
            return $spine;
        }

        $woven = collect();
        $showcaseIndex = 0;

        foreach ($spine as $index => $item) {
            $woven->push($item);

            $isIntervalBoundary = ($index + 1) % self::SHOWCASE_INTERVAL === 0;
            if ($isIntervalBoundary) {
                $woven->push(new FeedItem('showcase', $showcases[$showcaseIndex % $showcases->count()]));
                $showcaseIndex++;
            }
        }

        return $woven->values();
    }

    private function orderByDistance($query, float $lat, float $lng): Collection
    {
        $distances = DistanceQuery::nearbySellerDistances($lat, $lng, null);
        $products = $query->get();

        $products->each(function (Product $product) use ($distances) {
            $product->setAttribute('distance_km', $distances[$product->seller_id] ?? null);
        });

        return $products->sortBy(fn (Product $p) => $p->distance_km ?? PHP_FLOAT_MAX)->values();
    }
}

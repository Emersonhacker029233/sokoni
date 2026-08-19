<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Services\Geo\DistanceQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The one place product filtering/sorting/pagination logic lives —
 * extracted from `Api\ProductController::index` (which now delegates
 * here unchanged) so the public website's category/search pages apply
 * the exact same rules rather than a second, inevitably-drifting
 * implementation. CLAUDE.md's own instruction: "extract shared logic
 * into services where the web and API controllers both need it."
 *
 * "Verified sellers only" isn't wired as a real filter clause — every
 * publicly visible product already comes from a verified seller
 * (`Product::scopeVisible()`), that's the entire point of the
 * verification queue. The website still shows the checkbox (parity with
 * how buyers expect a marketplace to work, and a real trust signal worth
 * stating), it just can't narrow results further since nothing here is
 * ever unverified in the first place. See DECISIONS.md.
 */
class ProductSearchService
{
    public const PER_PAGE = 20;

    public const SPONSORED_SLOT_SIZE = 4;

    public function search(ProductSearchFilters $filters, int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $sort = $filters->sort ?: ($filters->hasLocation() ? 'nearby' : 'newest');

        $base = $this->baseQuery($filters);

        if ($filters->hasLocation()) {
            $paginated = $this->paginateByDistance($base, $filters, $sort, $page, $perPage);
        } else {
            $paginated = $this->applySort($base, $sort)->paginate($perPage, page: $page);
        }

        return $paginated;
    }

    /**
     * Up to `SPONSORED_SLOT_SIZE` currently-boosted products matching the
     * same filters (minus the sponsored/location-sort ones, which don't
     * apply to a pinned slot) — the website prepends these to page 1 only,
     * labelled "Sponsored", never counted against organic pagination.
     */
    public function sponsoredSlot(ProductSearchFilters $filters): Collection
    {
        return $this->baseQuery($filters)
            ->sponsoredActive()
            ->inRandomOrder()
            ->limit(self::SPONSORED_SLOT_SIZE)
            ->get();
    }

    private function baseQuery(ProductSearchFilters $filters): Builder
    {
        $query = Product::visible()
            ->with(['category', 'seller', 'media'])
            ->search($filters->query)
            ->inCategory($filters->categoryId)
            ->forSeller($filters->sellerId);

        if ($filters->region) {
            $query->whereHas('seller', fn (Builder $q) => $q->where('region', $filters->region));
        }

        if ($filters->condition) {
            $query->where('condition', $filters->condition);
        }

        if ($filters->priceMin !== null) {
            $query->where('price', '>=', $filters->priceMin);
        }

        if ($filters->priceMax !== null) {
            $query->where('price', '<=', $filters->priceMax);
        }

        if ($filters->hasVideo) {
            $query->whereHas('media', fn (Builder $q) => $q->where('type', 'video'));
        }

        if ($filters->sponsoredOnly) {
            $query->sponsoredActive();
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'trending' => $query->orderByDesc('views'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->orderByDesc('created_at'),
        };
    }

    /**
     * Nearby search combines a distance computation (SQL on MySQL, PHP
     * Haversine on SQLite — see DistanceQuery) with the rest of the
     * filters, then sorts/paginates in memory. Fine at Sokoni's scale (a
     * single-city seller base); see DistanceQuery's own docblock.
     */
    private function paginateByDistance(
        Builder $query,
        ProductSearchFilters $filters,
        string $sort,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $distances = DistanceQuery::nearbySellerDistances($filters->lat, $filters->lng, $filters->radiusKm);

        if (empty($distances)) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $products = $query->whereIn('seller_id', array_keys($distances))->get();

        $products->each(function (Product $product) use ($distances) {
            $product->setAttribute('distance_km', $distances[$product->seller_id]);
        });

        $sorted = (match ($sort) {
            'trending' => $products->sortByDesc('views'),
            'newest' => $products->sortByDesc('created_at'),
            'price_asc' => $products->sortBy('price'),
            'price_desc' => $products->sortByDesc('price'),
            default => $products->sortBy('distance_km'),
        })->values();

        $offset = ($page - 1) * $perPage;
        $slice = $sorted->slice($offset, $perPage)->values();

        return new LengthAwarePaginator($slice, $sorted->count(), $perPage, $page);
    }
}

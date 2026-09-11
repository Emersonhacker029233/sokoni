<?php

namespace App\Services\Catalog;

/**
 * One typed value object for every filter/sort input the API's
 * `ProductController::index` and the website's category/search pages both
 * accept — shared so the two surfaces can never quietly drift apart on
 * what a given query param means. See `ProductSearchService`.
 */
class ProductSearchFilters
{
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $sellerId = null,
        public readonly ?string $region = null,
        public readonly ?string $condition = null,
        public readonly ?int $priceMin = null,
        public readonly ?int $priceMax = null,
        public readonly bool $hasVideo = false,
        public readonly bool $sponsoredOnly = false,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?float $radiusKm = null,
        public readonly string $sort = 'newest',
        // C3 (tester feedback): Cars category-page filters — generic
        // product_attributes keys, not Cars-specific columns, so the same
        // two fields work unchanged if Real Estate ever filters on its
        // own attribute keys later.
        public readonly ?string $make = null,
        public readonly ?string $model = null,
        public readonly ?string $year = null,
    ) {}

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /** Same filters, a different (or no) category — used to count matches per category while ignoring the category filter itself. */
    public function withCategoryId(?int $categoryId): self
    {
        return new self(
            query: $this->query,
            categoryId: $categoryId,
            sellerId: $this->sellerId,
            region: $this->region,
            condition: $this->condition,
            priceMin: $this->priceMin,
            priceMax: $this->priceMax,
            hasVideo: $this->hasVideo,
            sponsoredOnly: $this->sponsoredOnly,
            lat: $this->lat,
            lng: $this->lng,
            radiusKm: $this->radiusKm,
            sort: $this->sort,
            make: $this->make,
            model: $this->model,
            year: $this->year,
        );
    }
}

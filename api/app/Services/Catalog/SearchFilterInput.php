<?php

namespace App\Services\Catalog;

use App\Support\TanzaniaRegions;
use Illuminate\Http\Request;

/**
 * Builds a {@see ProductSearchFilters} from the website's request query
 * string — the one place `?price_min=`/`?region=dar-es-salaam`/etc. get
 * parsed, shared between CategoryController and SearchController so both
 * pages accept the exact same filter vocabulary.
 */
class SearchFilterInput
{
    public static function fromRequest(Request $request, ?int $categoryId = null): ProductSearchFilters
    {
        $regionSlug = $request->string('region')->toString() ?: null;

        return new ProductSearchFilters(
            query: $request->string('q')->toString() ?: null,
            categoryId: $categoryId,
            region: $regionSlug ? TanzaniaRegions::fromSlug($regionSlug) : null,
            condition: $request->string('condition')->toString() ?: null,
            priceMin: $request->filled('price_min') ? (int) $request->input('price_min') : null,
            priceMax: $request->filled('price_max') ? (int) $request->input('price_max') : null,
            hasVideo: $request->boolean('has_video'),
            sponsoredOnly: $request->boolean('sponsored'),
            sort: $request->string('sort')->toString() ?: 'newest',
        );
    }
}

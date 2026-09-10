<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Catalog\CategoryCatalogService;
use App\Support\DarEsSalaam;
use Illuminate\View\View;

/**
 * TEMPORARILY UNCACHED: this used to wrap the home page's data in
 * Cache::remember(), caching a nested graph of Offer/Product/SellerProfile
 * models. Production started 500ing with __PHP_Incomplete_Class on
 * unserialize, surviving a full cache-store clear — see DECISIONS.md for
 * the incident writeup. Removed the caching layer entirely to get the
 * site back up; re-add once the actual cache store misconfiguration is
 * confirmed and fixed.
 */
class HomeController extends Controller
{
    public function __invoke(CategoryCatalogService $categories): View
    {
        $data = [
            'nearYou' => $this->nearYou(),
            'offers' => Offer::query()
                ->visible()
                ->with(['product.media', 'seller'])
                ->latest('starts_at')
                ->limit(8)
                ->get(),
            'featuredShops' => SellerProfile::query()
                ->verified()
                ->orderByDesc('rating_count')
                ->orderByDesc('rating_avg')
                ->limit(8)
                ->get(),
            'latest' => Product::query()
                ->visible()
                ->with(['category', 'seller', 'media'])
                // `latest()` alone orders by created_at DESC only — fine in
                // theory, but a batch of seeded/imported products routinely
                // shares the exact same created_at second (timestamps() has
                // no fractional precision), and MySQL doesn't guarantee any
                // particular order among ties. In practice that tie-break
                // came out as ascending id, i.e. oldest-batch-first, the
                // opposite of "newest first". `id` is monotonically
                // increasing with insertion order, so ordering by it DESC
                // as a tiebreaker makes "newest first" deterministic even
                // when many rows share a timestamp.
                ->latest()
                ->orderByDesc('id')
                ->limit(16)
                ->get(),
        ];

        return view('web.home', [
            ...$data,
            // Browse-categories grid only — a category with zero currently-
            // visible products would show "Agriculture 0", which reads as
            // "this marketplace is empty" rather than useful information
            // (tester feedback), so it's dropped from this section entirely
            // rather than shown with a count. CategoryCatalogService's own
            // withCounts() is left untouched: the header nav and direct
            // category-page links (/c/{category}) still need to resolve a
            // temporarily-empty category correctly, just not advertise it
            // as a browsing option on the home page.
            //
            // B6 (tester feedback): "Other" is exempt from that rule — it's
            // a permanent catch-all bucket, not a signal of how much
            // content exists, so a temporarily-empty "Other" reads
            // completely differently from a temporarily-empty real
            // category and shouldn't disappear the same way. sort_order
            // already places it last (CategorySeeder), so simply not
            // filtering it out is enough to get "last" for free.
            'categories' => $categories->withCounts()
                ->filter(fn ($category) => $category->products_count > 0 || $category->name_en === 'Other')
                ->values(),
            'title' => null,
            'description' => __('site.home_hero_subtitle'),
        ]);
    }

    private function nearYou(): \Illuminate\Support\Collection
    {
        $distances = \App\Services\Geo\DistanceQuery::nearbySellerDistances(DarEsSalaam::LAT, DarEsSalaam::LNG, 25);

        if (empty($distances)) {
            return collect();
        }

        $products = Product::query()
            ->visible()
            ->with(['category', 'seller', 'media'])
            ->whereIn('seller_id', array_keys($distances))
            ->get();

        return $products
            ->each(fn (Product $product) => $product->setAttribute('distance_km', $distances[$product->seller_id]))
            ->sortBy('distance_km')
            ->take(8)
            ->values();
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Catalog\CategoryCatalogService;
use App\Support\DarEsSalaam;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const TTL_SECONDS = 300;

    public function __invoke(CategoryCatalogService $categories): View
    {
        $data = Cache::remember('web:home:data', self::TTL_SECONDS, function () {
            return [
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
                    ->latest()
                    ->limit(16)
                    ->get(),
            ];
        });

        return view('web.home', [
            ...$data,
            'categories' => $categories->withCounts(),
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

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SellerProfile;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\SearchFilterInput;
use App\Support\TanzaniaRegions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Home search only ever matched product titles — a shop's own name
     * never turned up a result at all, on the home hero or this page
     * (they share this one endpoint), unless one of its products happened
     * to match too (tester feedback C2). Reuses the exact same shop_name/
     * handle match `StoresController` already uses for its own directory
     * search, so "shop" here means the same thing everywhere on the site.
     */
    private const SHOP_MATCH_LIMIT = 6;

    public function __invoke(Request $request, ProductSearchService $search): View
    {
        // category_id wasn't previously read from the query string here at
        // all — CategoryController is the only other caller of
        // fromRequest(), and it always passes its own already-resolved
        // category explicitly. The new sidebar tree filter (tester
        // feedback C3) needs the search page itself to accept it.
        $filters = SearchFilterInput::fromRequest($request, $request->integer('category_id') ?: null);

        $paginator = $search->search($filters, page: (int) $request->integer('page', 1));
        $paginator->appends($request->except('page'));

        $shops = $this->matchingShops($filters->query);

        // Category tree filter in the sidebar (tester feedback C3): counts
        // reflect every OTHER active filter, so a category the current
        // search wouldn't actually match shows a real 0 rather than being
        // silently omitted.
        $categoryTree = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
        $categoryCounts = $search->categoryCounts($filters, $categoryTree);

        return view('web.search', [
            'products' => $paginator,
            'shops' => $shops,
            'categoryTree' => $categoryTree,
            'categoryCounts' => $categoryCounts,
            // "Shops first when the query looks like a name" — a real shop
            // match *is* the signal that the query plausibly names a shop;
            // no match at all means it doesn't, and products lead as before.
            'shopsFirst' => $shops->isNotEmpty(),
            'sponsored' => $filters->query || $filters->sponsoredOnly ? collect() : $search->sponsoredSlot($filters),
            'filters' => $filters,
            'regions' => TanzaniaRegions::options(),
            'query' => $filters->query,
            'breadcrumbs' => [
                ['label' => 'Sokoni', 'url' => route('web.home')],
                ['label' => $filters->query ? "Search: \"{$filters->query}\"" : 'Search', 'url' => null],
            ],
            'title' => $filters->query ? "Search results for \"{$filters->query}\"" : 'Search',
            'description' => 'Search verified sellers and real listings across Tanzania on Sokoni.',
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, SellerProfile> */
    private function matchingShops(?string $term): \Illuminate\Support\Collection
    {
        if (! $term) {
            return collect();
        }

        return SellerProfile::query()
            ->verified()
            ->where(fn ($q) => $q->where('shop_name', 'like', "%{$term}%")->orWhere('handle', 'like', "%{$term}%"))
            ->withCount(['products' => fn ($q) => $q->visible()])
            ->orderByDesc('rating_count')
            ->orderByDesc('rating_avg')
            ->limit(self::SHOP_MATCH_LIMIT)
            ->get();
    }
}

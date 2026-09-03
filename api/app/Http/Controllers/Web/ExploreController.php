<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\SearchFilterInput;
use App\Support\TanzaniaRegions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Explore" (Gundua) — the newest listings across every category, one of
 * the website's 5 primary nav destinations. Deliberately reuses the exact
 * same filter/sort/pagination machinery `SearchController`/
 * `CategoryController` already share (`SearchFilterInput::fromRequest()`
 * already defaults `sort` to 'newest' with no category constraint, which
 * is precisely what this page is), rather than a second implementation —
 * the only real difference from a same-second Search page with an empty
 * query is that this is a dedicated browsing entry point, not a query
 * result.
 */
class ExploreController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search): View
    {
        $filters = SearchFilterInput::fromRequest($request);

        $paginator = $search->search($filters, page: (int) $request->integer('page', 1));
        $paginator->appends($request->except('page'));

        return view('web.explore', [
            'products' => $paginator,
            'sponsored' => $search->sponsoredSlot($filters),
            'filters' => $filters,
            'regions' => TanzaniaRegions::options(),
            'breadcrumbs' => [
                ['label' => 'Sokoni', 'url' => route('web.home')],
                ['label' => __('site.nav_explore'), 'url' => null],
            ],
            'title' => __('site.nav_explore'),
            'description' => __('site.explore_description'),
        ]);
    }
}

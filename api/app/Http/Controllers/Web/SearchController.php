<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\SearchFilterInput;
use App\Support\TanzaniaRegions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search): View
    {
        $filters = SearchFilterInput::fromRequest($request);

        $paginator = $search->search($filters, page: (int) $request->integer('page', 1));
        $paginator->appends($request->except('page'));

        return view('web.search', [
            'products' => $paginator,
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
}

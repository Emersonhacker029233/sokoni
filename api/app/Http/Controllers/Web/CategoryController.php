<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\SearchFilterInput;
use App\Support\TanzaniaRegions;
use App\Support\VehicleMakes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Category slugs are computed from name_en, not stored — renaming a
     * category (2026-09-03 client request: Agriculture -> Cereal & Legume,
     * Construction & Hardware -> Hardware) changes its slug too, breaking
     * every existing link and search-engine index entry pointing at the
     * old one unless it's explicitly redirected. A permanent (301) redirect
     * so link equity and any indexed pages carry over to the new slug.
     */
    private const SLUG_REDIRECTS = [
        'agriculture' => 'cereal-legume',
        'construction-hardware' => 'hardware',
        // C1 (client feedback): Food & Groceries -> Restaurant.
        'food-groceries' => 'restaurant',
    ];

    public function __invoke(
        Request $request,
        CategoryCatalogService $categories,
        ProductSearchService $search,
        string $category,
        ?string $child = null,
    ): View|RedirectResponse {
        if (isset(self::SLUG_REDIRECTS[$category])) {
            $newSlug = self::SLUG_REDIRECTS[$category];

            return $child
                ? redirect()->route('web.category', [$newSlug, $child], 301)
                : redirect()->route('web.category', $newSlug, 301);
        }

        // TEMPORARY DIAGNOSTIC — this whole body is wrapped to chase the
        // production-only 500 that doesn't reproduce locally (see
        // DECISIONS.md). Logs with a distinctive, grep-able marker and
        // re-throws unchanged, so normal error handling (the branded 500
        // page) still applies — this only adds visibility, it changes no
        // behavior. Remove the try/catch (unindent the body, drop the
        // catch block and the Log import) once the cause is confirmed.
        try {
            $parent = $categories->topLevelBySlugOrFail($category);
            $active = $parent;

            if ($child) {
                $active = $categories->childBySlugOrFail($parent, $child);
            }

            $filters = SearchFilterInput::fromRequest($request, categoryId: $active->id);

            $paginator = $search->search($filters, page: (int) $request->integer('page', 1));
            $paginator->appends($request->except('page'));

            $breadcrumbs = [
                ['label' => 'Sokoni', 'url' => route('web.home')],
                $child
                    ? ['label' => $parent->name(app()->getLocale()), 'url' => route('web.category', $categories->slug($parent))]
                    : ['label' => $parent->name(app()->getLocale()), 'url' => null],
            ];
            if ($child) {
                $breadcrumbs[] = ['label' => $active->name(app()->getLocale()), 'url' => null];
            }

            return view('web.category', [
                'category' => $active,
                'parentCategory' => $parent,
                'children' => $child ? collect() : $categories->children($parent),
                'products' => $paginator,
                'sponsored' => $search->sponsoredSlot($filters),
                'filters' => $filters,
                'regions' => TanzaniaRegions::options(),
                // C3 (tester feedback): Cars category-page filters — the
                // parent (browsing "Vehicles & Parts" -> "Cars") and the
                // child itself both count, same as any other subcategory page.
                'showVehicleFilters' => $active->isCars(),
                'vehicleMakes' => VehicleMakes::makes(),
                'vehicleMakeModels' => VehicleMakes::ALL,
                'vehicleYears' => VehicleMakes::years(),
                'breadcrumbs' => $breadcrumbs,
                'title' => $active->name(app()->getLocale()),
                'description' => "Browse {$active->name('en')} listings on Sokoni — verified sellers, real photos, near you.",
            ]);
        } catch (\Throwable $e) {
            Log::error('[CATEGORY_500_DIAGNOSTIC] '.get_class($e).': '.$e->getMessage(), [
                'category_param' => $category,
                'child_param' => $child,
                'query' => $request->query(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect(explode("\n", $e->getTraceAsString()))->take(15)->implode("\n"),
            ]);

            throw $e;
        }
    }
}

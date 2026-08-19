<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\SearchFilterInput;
use App\Support\TanzaniaRegions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __invoke(
        Request $request,
        CategoryCatalogService $categories,
        ProductSearchService $search,
        string $category,
        ?string $child = null,
    ): View {
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
            'breadcrumbs' => $breadcrumbs,
            'title' => $active->name(app()->getLocale()),
            'description' => "Browse {$active->name('en')} listings on Sokoni — verified sellers, real photos, near you.",
        ]);
    }
}

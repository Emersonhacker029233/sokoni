<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SellerProfile;
use App\Support\TanzaniaRegions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Stores" (Maduka) — a directory of every verified shop, one of the
 * website's 5 primary nav destinations. Nothing else on the site lets a
 * visitor browse sellers directly (the app's own discovery is
 * product-first); this is a genuinely new page, not a reskin of an
 * existing one.
 */
class StoresController extends Controller
{
    private const PER_PAGE = 24;

    public function __invoke(Request $request): View
    {
        $query = SellerProfile::query()
            ->verified()
            ->with('category')
            ->withCount(['products' => fn ($q) => $q->visible()]);

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(fn ($q) => $q->where('shop_name', 'like', "%{$term}%")->orWhere('handle', 'like', "%{$term}%"));
        }

        if ($request->filled('region')) {
            $region = TanzaniaRegions::fromSlug($request->string('region')->toString());
            if ($region) {
                $query->where('region', $region);
            }
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $sort = $request->string('sort')->toString() ?: 'rating';
        match ($sort) {
            'newest' => $query->orderByDesc('verified_at'),
            'listings' => $query->orderByDesc('products_count'),
            default => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
        };

        $stores = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('web.stores', [
            'stores' => $stores,
            // A6 (tester feedback) audit: same missing whereNull('parent_id')
            // found on the seller registration form — a shop's own category
            // (which this filter dropdown matches against) is always
            // top-level, so subcategories have no business appearing here.
            'categories' => Category::whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => TanzaniaRegions::options(),
            'query' => $request->string('q')->toString(),
            'sort' => $sort,
            'title' => __('site.nav_stores'),
            'description' => __('site.stores_description'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\Settings;
use App\Support\VehicleMakes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "The ability to post a listing from the web" (CLAUDE.md website Section
 * 6) — reuses the exact same FormRequests (`ProductStoreRequest`/
 * `ProductUpdateRequest`) the API's `Api\ProductController` uses, so a
 * listing created here follows identical validation. Photo management is
 * a separate concern handled entirely by `ShopProductMediaController`'s
 * AJAX endpoints (tester feedback: the old single inline `images[]` field
 * gave sellers no way to see, remove, or reorder what they'd picked, which
 * read as "only one photo works" even though the server always accepted
 * more) — a product must exist before photos can be attached to it, which
 * is why `store()` below lands the seller straight on the edit page rather
 * than the list, exactly like the Flutter app's own product form already
 * does ("Saved — now add photos").
 */
class ShopProductsController extends Controller
{
    public function index(): View
    {
        $seller = Auth::user()->sellerProfile()->firstOrFail();

        $products = $seller->products()->with(['category', 'media'])->latest()->paginate(20)->withQueryString();

        return view('web.account.shop-products', ['products' => $products, 'title' => 'My products']);
    }

    public function create(): View
    {
        return view('web.account.shop-product-form', [
            'product' => null,
            ...$this->categoryFormData(),
            'title' => 'New product',
            'maxMediaPerProduct' => Settings::maxMediaPerProduct(),
        ]);
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $data = $request->validated();
        $product = $seller->products()->create(collect($data)->except(['make', 'model', 'year'])->all());
        $this->syncVehicleAttributes($product, $data);

        return redirect()->route('web.account.shop.products.edit', $product)
            ->with('status', 'Product created — now add your photos below.');
    }

    public function edit(Product $product): View
    {
        abort_unless(Auth::user()->can('update', $product), 403);

        return view('web.account.shop-product-form', [
            'product' => $product->load(['media', 'category', 'productAttributes']),
            ...$this->categoryFormData(),
            'title' => 'Edit '.$product->title,
            'maxMediaPerProduct' => Settings::maxMediaPerProduct(),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $product->update(collect($data)->except(['make', 'model', 'year'])->all());
        $this->syncVehicleAttributes($product, $data);

        return redirect()->route('web.account.shop.products')->with('status', 'Product updated.');
    }

    /**
     * C3 (tester feedback): identical to Api\ProductController's own
     * helper of the same name — Make/Model live in the generic
     * `product_attributes` table, only touched when the request actually
     * included them.
     */
    private function syncVehicleAttributes(Product $product, array $data): void
    {
        foreach (['make', 'model', 'year'] as $key) {
            if (array_key_exists($key, $data)) {
                $product->productAttributes()->updateOrCreate(['key' => $key], ['value' => (string) $data[$key]]);
            }
        }
    }

    /**
     * Top-level categories for the primary select, plus every active
     * subcategory grouped by parent id (as a plain array, JSON-embedded
     * client-side) so the "Subcategory" select can filter instantly
     * without a round trip as the seller changes the top-level pick.
     */
    private function categoryFormData(): array
    {
        $categories = Category::whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get();

        $subcategoriesByParent = Category::whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id')
            ->map(fn ($group) => $group->map(fn (Category $c) => ['id' => $c->id, 'name_en' => $c->name_en])->values())
            ->toArray();

        // C3 (tester feedback): "Cars" is always a subcategory (never
        // top-level), so the form's Make/Model block needs to know its id
        // to show itself when that's the selected child — matched by
        // name_en, same as Category::isCars().
        $carsCategoryId = Category::where('name_en', 'Cars')->value('id');

        return compact('categories', 'subcategoriesByParent', 'carsCategoryId') + [
            'vehicleMakeModels' => VehicleMakes::ALL,
            'vehicleYears' => VehicleMakes::years(),
        ];
    }
}

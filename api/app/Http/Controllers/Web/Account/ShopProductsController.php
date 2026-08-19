<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Media\ImageVariants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "The ability to post a listing from the web" (CLAUDE.md website Section
 * 6) — reuses the exact same FormRequests (`ProductStoreRequest`/
 * `ProductUpdateRequest`) and `ImageVariants` service the API's
 * `Api\ProductController`/`ProductMediaController` use, so a listing
 * created here follows identical validation and produces identical
 * thumb/card/full media variants.
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
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'title' => 'New product',
        ]);
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $product = $seller->products()->create($request->validated());

        $this->attachUploadedImages($request, $product);

        return redirect()->route('web.account.shop.products')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        abort_unless(Auth::user()->can('update', $product), 403);

        return view('web.account.shop-product-form', [
            'product' => $product->load('media'),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'title' => 'Edit '.$product->title,
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());
        $this->attachUploadedImages($request, $product);

        return redirect()->route('web.account.shop.products')->with('status', 'Product updated.');
    }

    private function attachUploadedImages(ProductUpdateRequest|ProductStoreRequest $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        // Not part of ProductStoreRequest/ProductUpdateRequest — those are
        // shared with the JSON-only API, which never accepts file uploads
        // on this endpoint (see ProductMediaController for that). Validated
        // separately here rather than adding a web-only concern to a
        // request class the API also uses.
        $request->validate(['images.*' => ['image', 'max:8192']]);

        $directory = "products/{$product->id}";
        $nextSort = $product->media()->count();

        foreach ($request->file('images') as $index => $file) {
            if ($product->media()->count() >= \App\Support\Settings::maxMediaPerProduct()) {
                break;
            }

            $variants = ImageVariants::generate($file, $directory);

            $product->media()->create([
                'type' => 'image',
                'path' => Storage::disk('public')->url($variants['full']),
                'card_path' => Storage::disk('public')->url($variants['card']),
                'thumb_path' => Storage::disk('public')->url($variants['thumb']),
                'sort' => $nextSort + $index,
            ]);
        }
    }
}

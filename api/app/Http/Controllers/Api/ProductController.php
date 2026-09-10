<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BoostProductRequest;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductSearchFilters;
use App\Services\Catalog\ProductSearchService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly ProductSearchService $search) {}

    public function index(ProductIndexRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $hasLocation = $request->filled('lat') && $request->filled('lng');

        $filters = new ProductSearchFilters(
            query: $request->string('q')->toString() ?: null,
            categoryId: $request->integer('category_id') ?: null,
            sellerId: $request->integer('seller_id') ?: null,
            lat: $request->float('lat') ?: null,
            lng: $request->float('lng') ?: null,
            radiusKm: $request->float('radius_km') ?: null,
            sort: $request->string('sort')->toString() ?: ($hasLocation ? 'nearby' : 'newest'),
            // C3 (tester feedback): the app's own Cars category filter —
            // same product_attributes match the website's category page uses.
            make: $request->string('make')->toString() ?: null,
            model: $request->string('model')->toString() ?: null,
        );

        $paginated = $this->search->search($filters, page: (int) ($request->integer('page') ?: 1), perPage: self::PER_PAGE);

        return ProductResource::collection($paginated);
    }

    /**
     * The signed-in seller's own products — unlike the public index, this
     * bypasses `visible()` so hidden/pending products still show up on the
     * seller's own My Shop dashboard (CLAUDE.md feature 4: "Pending
     * sellers can build their shop and add products, but products stay
     * hidden from the public feed until verified").
     */
    public function mine(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $products = $seller->products()
            ->with(['category', 'media'])
            ->latest()
            ->paginate(self::PER_PAGE);

        return ProductResource::collection($products);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $isOwner = $request->user()?->sellerProfileId() === $product->seller_id;
        $isVisible = $product->is_active && ! $product->is_hidden && $product->seller->isVerified();
        abort_unless($isOwner || $isVisible, 404);

        $product->load(['category', 'seller', 'media', 'productAttributes']);
        $product->increment('views');

        return new ProductResource($product);
    }

    public function store(ProductStoreRequest $request): ProductResource
    {
        // Query fresh rather than the `sellerProfile` relation: it may
        // already be cached as null on this request's user object from an
        // earlier access (e.g. UserResource) before a seller profile
        // existed — see User::isSeller().
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $data = $request->validated();
        $product = $seller->products()->create(collect($data)->except(['make', 'model'])->all());
        $this->syncVehicleAttributes($product, $data);
        $product->load(['category', 'seller', 'media', 'productAttributes']);

        return new ProductResource($product);
    }

    public function update(ProductUpdateRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();
        $product->update(collect($data)->except(['make', 'model'])->all());
        $this->syncVehicleAttributes($product, $data);
        $product->load(['category', 'seller', 'media', 'productAttributes']);

        return new ProductResource($product);
    }

    /**
     * C3 (tester feedback): Make/Model live in the generic
     * `product_attributes` table, not their own columns — only touched
     * when the request actually included them (an update that never
     * mentions make/model, e.g. changing price only, leaves whatever
     * attributes already exist untouched rather than clearing them).
     */
    private function syncVehicleAttributes(Product $product, array $data): void
    {
        foreach (['make', 'model'] as $key) {
            if (array_key_exists($key, $data)) {
                $product->productAttributes()->updateOrCreate(['key' => $key], ['value' => $data[$key]]);
            }
        }
    }

    public function destroy(Request $request, Product $product): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    /**
     * Boost/un-boost a Listing into the "For You" feed's sponsored slot
     * (CLAUDE.md Part 3) — no payment flow yet, so this is a plain
     * seller-toggleable flag rather than anything billing-related; see
     * `FeedService` for how sponsored Listings are ranked, and
     * `sponsor_contact_method` for the one contact action the seller
     * chooses to surface on the card (chat/whatsapp/call).
     */
    public function boost(BoostProductRequest $request, Product $product): ProductResource
    {
        $isSponsored = $request->boolean('is_sponsored');

        $product->update([
            'is_sponsored' => $isSponsored,
            'sponsored_until' => $isSponsored ? now()->addDays($request->integer('duration_days')) : null,
            'sponsor_contact_method' => $isSponsored ? $request->string('contact_method')->toString() : null,
        ]);
        $product->load(['category', 'seller', 'media']);

        return new ProductResource($product);
    }
}

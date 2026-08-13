<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Geo\DistanceQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductController extends Controller
{
    private const PER_PAGE = 20;

    public function index(ProductIndexRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $lat = $request->float('lat');
        $lng = $request->float('lng');
        $hasLocation = $request->filled('lat') && $request->filled('lng');
        $sort = $request->string('sort')->toString() ?: ($hasLocation ? 'nearby' : 'newest');
        $page = (int) ($request->integer('page') ?: 1);

        $query = Product::visible()
            ->with(['category', 'seller', 'media'])
            ->search($request->string('q')->toString() ?: null)
            ->inCategory($request->integer('category_id') ?: null)
            ->forSeller($request->integer('seller_id') ?: null);

        if ($hasLocation) {
            $paginated = $this->paginateByDistance($query, $lat, $lng, $request->float('radius_km') ?: null, $sort, $page);
        } else {
            $paginated = (match ($sort) {
                'trending' => $query->orderByDesc('views'),
                default => $query->orderByDesc('created_at'),
            })->paginate(self::PER_PAGE, page: $page);
        }

        return ProductResource::collection($paginated);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $isOwner = $request->user()?->sellerProfileId() === $product->seller_id;
        $isVisible = $product->is_active && ! $product->is_hidden && $product->seller->isVerified();
        abort_unless($isOwner || $isVisible, 404);

        $product->load(['category', 'seller', 'media']);
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
        $product = $seller->products()->create($request->validated());
        $product->load(['category', 'seller', 'media']);

        return new ProductResource($product);
    }

    public function update(ProductUpdateRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        $product->load(['category', 'seller', 'media']);

        return new ProductResource($product);
    }

    public function destroy(Request $request, Product $product): \Illuminate\Http\JsonResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    /**
     * Nearby search combines a distance computation (SQL on MySQL, PHP
     * Haversine on SQLite — see DistanceQuery) with the rest of the
     * filters, then sorts/paginates in memory. Fine at Sokoni's scale
     * (a single-city seller base); see DistanceQuery's docblock.
     */
    private function paginateByDistance(
        $query,
        float $lat,
        float $lng,
        ?float $radiusKm,
        string $sort,
        int $page,
    ): LengthAwarePaginator {
        $distances = DistanceQuery::nearbySellerDistances($lat, $lng, $radiusKm);

        if (empty($distances)) {
            return new LengthAwarePaginator([], 0, self::PER_PAGE, $page);
        }

        $products = $query->whereIn('seller_id', array_keys($distances))->get();

        $products->each(function (Product $product) use ($distances) {
            $product->setAttribute('distance_km', $distances[$product->seller_id]);
        });

        $sorted = (match ($sort) {
            'trending' => $products->sortByDesc('views'),
            'newest' => $products->sortByDesc('created_at'),
            default => $products->sortBy('distance_km'),
        })->values();

        $offset = ($page - 1) * self::PER_PAGE;
        $slice = $sorted->slice($offset, self::PER_PAGE)->values();

        return new LengthAwarePaginator($slice, $sorted->count(), self::PER_PAGE, $page);
    }
}

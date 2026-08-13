<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $request->user()->favorites()->with(['category', 'seller', 'media'])->paginate(20);

        return ProductResource::collection($products);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $request->user()->favorites()->syncWithoutDetaching([$product->id]);

        return response()->json(['message' => 'Added to favourites.']);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->favorites()->detach($product->id);

        return response()->json(['message' => 'Removed from favourites.']);
    }
}

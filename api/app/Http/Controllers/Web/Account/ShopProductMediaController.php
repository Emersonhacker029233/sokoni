<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductMediaStoreRequest;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Media\ImageVariants;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The web product form's AJAX photo manager (tester feedback item 1) —
 * one photo per request, with real per-file progress on the client, in
 * place of the old single `images[]` field that quietly accepted several
 * files but gave a seller no way to see, remove, or reorder them before
 * or after upload. Mirrors `Api\ProductMediaController` exactly (same
 * `ImageVariants` pipeline, same `Settings::maxMediaPerProduct()` cap,
 * same ownership rule) but authenticated via the `web` session guard
 * instead of Sanctum, and images-only — the website's product form has
 * never offered video upload, so that scope wasn't extended here.
 */
class ShopProductMediaController extends Controller
{
    public function store(ProductMediaStoreRequest $request, Product $product): JsonResponse
    {
        $maxItems = Settings::maxMediaPerProduct();
        if ($product->media()->count() >= $maxItems) {
            throw ValidationException::withMessages(['file' => "A product can have at most {$maxItems} photos."]);
        }

        $directory = "products/{$product->id}";
        $variants = ImageVariants::generate($request->file('file'), $directory);

        $media = $product->media()->create([
            'type' => 'image',
            'path' => Storage::disk('public')->url($variants['full']),
            'card_path' => Storage::disk('public')->url($variants['card']),
            'thumb_path' => Storage::disk('public')->url($variants['thumb']),
            'sort' => $request->integer('sort') ?: $product->media()->count(),
        ]);

        return response()->json([
            'id' => $media->id,
            'thumb_path' => $media->thumb_path,
            'sort' => $media->sort,
        ], 201);
    }

    public function destroy(Product $product, ProductMedia $media): JsonResponse
    {
        $this->authorize('update', $product);
        abort_unless($media->product_id === $product->id, 404);

        $media->deleteWithFiles();

        return response()->json(['message' => 'Photo removed.']);
    }

    /**
     * Persists the seller's drag-to-reorder — takes the *complete* new
     * order as a list of this product's own media ids (not a partial
     * move), so there's no ambiguity about where an id not mentioned
     * should end up, and no way to smuggle in another seller's media id
     * to have it silently adopted.
     */
    public function reorder(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:product_media,id'],
        ]);

        $ids = collect($validated['order']);
        $ownIds = $product->media()->pluck('id');
        abort_unless($ids->count() === $ownIds->count() && $ids->diff($ownIds)->isEmpty(), 422);

        foreach ($ids->values() as $sort => $id) {
            ProductMedia::whereKey($id)->update(['sort' => $sort]);
        }

        return response()->json(['message' => 'Order saved.']);
    }
}

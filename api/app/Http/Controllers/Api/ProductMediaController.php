<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductMediaStoreRequest;
use App\Http\Resources\ProductMediaResource;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Media\ImageVariants;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductMediaController extends Controller
{
    public function store(ProductMediaStoreRequest $request, Product $product): ProductMediaResource
    {
        // CLAUDE.md feature 7: "Up to 8 items per product" — 8 is the
        // config default (config/sokoni.php), admin-overridable via the
        // Settings page (App\Support\Settings).
        $maxItems = Settings::maxMediaPerProduct();
        if ($product->media()->count() >= $maxItems) {
            throw ValidationException::withMessages(['file' => "A product can have at most {$maxItems} photos/videos."]);
        }

        $directory = "products/{$product->id}";
        $type = $request->string('type')->toString();

        if ($type === 'image') {
            $variants = ImageVariants::generate($request->file('file'), $directory);

            $media = $product->media()->create([
                'type' => 'image',
                'path' => Storage::disk('public')->url($variants['full']),
                'card_path' => Storage::disk('public')->url($variants['card']),
                'thumb_path' => Storage::disk('public')->url($variants['thumb']),
                'sort' => $request->integer('sort') ?: $product->media()->count(),
            ]);
        } else {
            $videoPath = $request->file('file')->store($directory, 'public');
            // The poster frame is just a display thumbnail, not a
            // carousel-quality asset — one size (card) is enough.
            $thumbVariants = ImageVariants::generate($request->file('thumbnail'), $directory);

            $media = $product->media()->create([
                'type' => 'video',
                'path' => Storage::disk('public')->url($videoPath),
                'thumb_path' => Storage::disk('public')->url($thumbVariants['card']),
                'duration' => $request->integer('duration'),
                'sort' => $request->integer('sort') ?: $product->media()->count(),
            ]);
        }

        return new ProductMediaResource($media);
    }

    public function destroy(Product $product, ProductMedia $media): JsonResponse
    {
        $this->authorize('update', $product);
        abort_unless($media->product_id === $product->id, 404);

        $media->deleteWithFiles();

        return response()->json(['message' => 'Media deleted.']);
    }

    /**
     * Part 3 (client feedback): "reordering so the seller chooses the
     * cover image" — mirrors Web\Account\ShopProductMediaController's own
     * reorder() exactly (same complete-order-required, same ownership
     * check), just Sanctum-authenticated instead of session-authenticated
     * so the app can reuse it too. Takes the *complete* new order as a
     * list of this product's own media ids (not a partial move), so
     * there's no ambiguity about where an id not mentioned should end up,
     * and no way to smuggle in another seller's media id to have it
     * silently adopted.
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

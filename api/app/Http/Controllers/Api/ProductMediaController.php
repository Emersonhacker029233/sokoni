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
}

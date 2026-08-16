<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUpdateRequest;
use App\Http\Resources\UpdateResource;
use App\Models\SellerProfile;
use App\Models\Update;
use App\Services\Media\ImageVariants;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    /** Larger than the usual 20 — the tray groups many sellers' single-item Updates into one horizontal strip. */
    private const PER_PAGE = 50;

    public function __construct(private readonly PushNotifier $push) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Update::query()->visible()->with(['seller', 'product.media']);

        if ($request->boolean('following')) {
            abort_unless($request->user(), 401);
            $query->followedBy($request->user()->id);
        }

        if ($sellerId = $request->integer('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        $updates = $query->orderByDesc('created_at')->paginate(self::PER_PAGE);

        return UpdateResource::collection($updates);
    }

    public function store(StoreUpdateRequest $request): JsonResponse
    {
        // Query fresh, not the cached relation — see ProductController::store.
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $directory = "updates/{$seller->id}";
        $type = $request->string('type')->toString();

        if ($type === 'image') {
            $variants = ImageVariants::generate($request->file('file'), $directory);
            $mediaPath = Storage::disk('public')->url($variants['card']);
            $thumbPath = Storage::disk('public')->url($variants['thumb']);
        } else {
            $videoPath = $request->file('file')->store($directory, 'public');
            // Poster frame, client-extracted — same reasoning as product video media.
            $thumbVariants = ImageVariants::generate($request->file('thumbnail'), $directory);
            $mediaPath = Storage::disk('public')->url($videoPath);
            $thumbPath = Storage::disk('public')->url($thumbVariants['card']);
        }

        $update = $seller->updates()->create([
            'type' => $type,
            'media_path' => $mediaPath,
            'thumb_path' => $thumbPath,
            'caption' => $request->string('caption')->toString() ?: null,
            'product_id' => $request->integer('product_id') ?: null,
            'expires_at' => now()->addDay(),
        ]);
        $update->load(['seller', 'product.media']);

        $this->notifyFollowers(
            $seller,
            'New update from '.$seller->shop_name,
            $update->caption ?: 'Check out their latest update.',
            ['update_id' => $update->id],
        );

        return (new UpdateResource($update))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Update $update): JsonResponse
    {
        $this->authorize('delete', $update);

        foreach ([$update->media_path, $update->thumb_path] as $url) {
            if ($url) {
                $relative = str($url)->after(Storage::disk('public')->url(''));
                Storage::disk('public')->delete($relative);
            }
        }

        $update->delete();

        return response()->json(['message' => 'Update deleted.']);
    }

    private function notifyFollowers(SellerProfile $seller, string $title, string $body, array $data): void
    {
        foreach ($seller->followers as $follower) {
            $this->push->notify($follower, $title, $body, $data);
        }
    }
}

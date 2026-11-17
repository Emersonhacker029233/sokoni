<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShowcaseRequest;
use App\Http\Resources\ShowcaseResource;
use App\Models\Showcase;
use App\Services\Media\ImageVariants;
use App\Services\Moderation\BlockList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ShowcaseController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): AnonymousResourceCollection
    {
        $viewer = BlockList::viewer($request);
        $query = Showcase::query()->visible()->notBlockedBy($viewer)->with(['seller', 'product.media']);

        if ($request->boolean('following')) {
            abort_unless($viewer, 401);
            $query->followedBy($viewer->id);
        }

        if ($sellerId = $request->integer('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        $showcases = $query->orderByDesc('created_at')->paginate(self::PER_PAGE);

        return ShowcaseResource::collection($showcases);
    }

    public function show(Request $request, Showcase $showcase): ShowcaseResource|JsonResponse
    {
        $showcase->load(['seller.user', 'product.media']);
        $viewer = BlockList::viewer($request);
        $isOwner = $viewer !== null && $viewer->id === $showcase->seller?->user_id;
        abort_unless($isOwner || $showcase->isPubliclyVisible(), 404);

        if (! $isOwner && app(BlockList::class)->hasBlocked($viewer, $showcase->seller?->user_id)) {
            return BlockList::forbidden('You blocked this account.', $showcase->seller?->user_id);
        }

        $showcase->increment('views');

        return new ShowcaseResource($showcase);
    }

    public function store(StoreShowcaseRequest $request): JsonResponse
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $directory = "showcases/{$seller->id}";

        $videoPath = $request->file('file')->store($directory, 'public');
        // Poster frame, client-extracted — same reasoning as product video media.
        $thumbVariants = ImageVariants::generate($request->file('thumbnail'), $directory);

        $showcase = $seller->showcases()->create([
            'product_id' => $request->integer('product_id'),
            'video_path' => Storage::disk('public')->url($videoPath),
            'thumb_path' => Storage::disk('public')->url($thumbVariants['card']),
            'caption' => $request->string('caption')->toString() ?: null,
            'duration' => $request->integer('duration'),
        ]);
        $showcase->load(['seller', 'product.media']);

        return (new ShowcaseResource($showcase))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Showcase $showcase): JsonResponse
    {
        $this->authorize('delete', $showcase);

        foreach ([$showcase->video_path, $showcase->thumb_path] as $url) {
            if ($url) {
                $relative = str($url)->after(Storage::disk('public')->url(''));
                Storage::disk('public')->delete($relative);
            }
        }

        $showcase->delete();

        return response()->json(['message' => 'Showcase deleted.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyReviewRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Review;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Order $order): JsonResponse
    {
        $review = Review::query()->create([
            ...$request->validated(),
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'buyer_id' => $order->buyer_id,
        ]);

        return (new ReviewResource($review->load('buyer')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function reply(ReplyReviewRequest $request, Review $review): ReviewResource
    {
        // forceFill: reply/replied_at are deliberately excluded from
        // Review's #[Fillable] — a buyer must never be able to set them
        // via the review-creation endpoint.
        $review->forceFill(['reply' => $request->string('reply'), 'replied_at' => now()])->save();

        return new ReviewResource($review->load('buyer'));
    }

    /** Paginated reviews for a shop, for the seller profile's review list + distribution bar. */
    public function forSeller(string $handle): AnonymousResourceCollection
    {
        $seller = SellerProfile::query()->where('handle', $handle)->firstOrFail();

        $reviews = $seller->reviews()->with('buyer')->latest()->paginate(20);

        return ReviewResource::collection($reviews)->additional([
            'meta' => [
                'distribution' => $seller->reviews()
                    ->selectRaw('rating, count(*) as total')
                    ->groupBy('rating')
                    ->pluck('total', 'rating'),
            ],
        ]);
    }
}

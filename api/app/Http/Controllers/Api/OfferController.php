<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OfferController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly PushNotifier $push) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Offer::query()->visible()->with(['seller', 'product.media']);

        if ($request->boolean('following')) {
            abort_unless($request->user(), 401);
            $query->followedBy($request->user()->id);
        }

        if ($sellerId = $request->integer('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        // Soonest-ending first — matches the urgency a countdown row implies.
        $offers = $query->orderBy('ends_at')->paginate(self::PER_PAGE);

        return OfferResource::collection($offers);
    }

    public function store(StoreOfferRequest $request): JsonResponse
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $product = Product::query()->findOrFail($request->integer('product_id'));

        $offer = $seller->offers()->create([
            'product_id' => $product->id,
            'discount_type' => $request->string('discount_type')->toString(),
            'discount_value' => $request->input('discount_value'),
            'price_snapshot' => $product->price,
            'starts_at' => now(),
            'ends_at' => now()->addDays($request->integer('duration_days')),
        ]);
        $offer->load(['seller', 'product.media']);

        $this->notifyFollowers(
            $seller,
            'New offer from '.$seller->shop_name,
            "Save on {$product->title} for a limited time.",
            ['offer_id' => $offer->id],
        );

        return (new OfferResource($offer))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Offer $offer): JsonResponse
    {
        $this->authorize('delete', $offer);
        $offer->delete();

        return response()->json(['message' => 'Offer ended.']);
    }

    private function notifyFollowers(SellerProfile $seller, string $title, string $body, array $data): void
    {
        foreach ($seller->followers as $follower) {
            $this->push->notify($follower, $title, $body, $data);
        }
    }
}

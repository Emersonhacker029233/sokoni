<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerProfileResource;
use App\Models\Customer;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** "Customer"/"Mteja" follow relationship (CLAUDE.md Part 3) — replaces the generic "follower" term. */
class CustomerController extends Controller
{
    /** Shops the signed-in buyer follows — powers a "Following" management list. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sellers = $request->user()->following()->with('category')->paginate(20);

        return SellerProfileResource::collection($sellers);
    }

    public function store(Request $request, string $handle): JsonResponse
    {
        $seller = SellerProfile::query()->where('handle', $handle)->firstOrFail();
        abort_if($request->user()->sellerProfileId() === $seller->id, 422, "You can't follow your own shop.");

        // firstOrCreate fires Customer's 'created' event (CustomerObserver)
        // only when a row is actually inserted, keeping this idempotent.
        Customer::query()->firstOrCreate(['buyer_id' => $request->user()->id, 'seller_id' => $seller->id]);

        return response()->json(['message' => 'Following.']);
    }

    public function destroy(Request $request, string $handle): JsonResponse
    {
        $seller = SellerProfile::query()->where('handle', $handle)->firstOrFail();

        // A model delete (not a query-builder ->delete()) so CustomerObserver's
        // 'deleted' hook actually fires and recalculates customer_count.
        Customer::query()
            ->where('buyer_id', $request->user()->id)
            ->where('seller_id', $seller->id)
            ->first()
            ?->delete();

        return response()->json(['message' => 'Unfollowed.']);
    }
}

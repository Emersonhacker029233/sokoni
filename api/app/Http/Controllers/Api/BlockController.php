<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlockRequest;
use App\Http\Resources\BlockedUserResource;
use App\Models\Customer;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Moderation\BlockList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Block and unblock (Apple Guideline 1.2: "a mechanism for users to block
 * abusive users", which "should also notify the developer"). Every list
 * the blocker reads leaves the blocked user's content out from the next
 * request on; see BlockList and the `notBlockedBy` scopes.
 */
class BlockController extends Controller
{
    public function __construct(private readonly BlockList $blocks) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $blocks = UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->with('blocked.sellerProfile')
            ->latest()
            ->get();

        return BlockedUserResource::collection($blocks);
    }

    public function store(StoreBlockRequest $request): JsonResponse
    {
        $me = $request->user();
        $target = $request->filled('seller_id')
            ? SellerProfile::query()->find($request->integer('seller_id'))?->user
            : User::query()->find($request->integer('user_id'));

        if ($target === null) {
            throw ValidationException::withMessages(['user_id' => 'Not found.']);
        }
        if ($target->id === $me->id) {
            throw ValidationException::withMessages(['user_id' => "You can't block yourself."]);
        }

        $block = UserBlock::query()->firstOrCreate(
            ['blocker_id' => $me->id, 'blocked_id' => $target->id],
            ['reason' => $request->input('reason')],
        );

        if ($block->wasRecentlyCreated) {
            // Following a shop you blocked would keep its updates and
            // offers coming. Deleted one by one so CustomerObserver keeps
            // the shop's follower count right.
            if ($shop = $target->sellerProfile) {
                Customer::query()->where('buyer_id', $me->id)->where('seller_id', $shop->id)->get()->each->delete();
            }

            // Filed as a report so every block reaches the moderators the
            // same way, with the same 24-hour clock (ReportObserver alerts).
            Report::query()->create([
                'reporter_id' => $me->id,
                'reportable_type' => User::class,
                'reportable_id' => $target->id,
                'reason' => 'blocked',
                'note' => $request->filled('reason')
                    ? 'Reason given: '.Report::REASONS[$request->string('reason')->toString()]
                    : null,
            ]);
        }

        $this->blocks->forget($me);

        return (new BlockedUserResource($block->load('blocked.sellerProfile')))
            ->response()
            ->setStatusCode($block->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user)
            ->delete();

        $this->blocks->forget($request->user());

        return response()->json(['message' => 'Unblocked.']);
    }
}

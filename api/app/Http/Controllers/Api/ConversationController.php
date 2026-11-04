<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\SellerProfile;
use App\Services\Moderation\BlockList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function __construct(private readonly BlockList $blocks) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $sellerId = $user->sellerProfileId();

        $query = Conversation::query()
            ->where(function ($q) use ($user, $sellerId) {
                $q->where('buyer_id', $user->id);
                if ($sellerId) {
                    $q->orWhere('seller_id', $sellerId);
                }
            });

        // Threads with anyone the user blocked disappear from their list.
        // The other person is either the buyer or the shop's owner, and
        // the user never blocks themselves, so both filters are safe.
        $blocked = $this->blocks->blockedUserIds($user);
        if ($blocked !== []) {
            $query->whereNotIn('buyer_id', $blocked)
                ->whereNotIn('seller_id', SellerProfile::withTrashed()->whereIn('user_id', $blocked)->select('id'));
        }

        $conversations = $query
            ->with(['buyer', 'seller', 'product', 'messages'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource|JsonResponse
    {
        $this->authorize('view', $conversation);

        $other = $conversation->otherParty($request->user());
        if ($this->blocks->hasBlocked($request->user(), $other?->id)) {
            return BlockList::forbidden('You blocked this account.', $other?->id);
        }

        $conversation->load(['buyer', 'seller', 'product', 'messages']);

        return new ConversationResource($conversation);
    }

    /** Pinned per-product thread context (CLAUDE.md feature 5): buyer starts (or resumes) a thread from a product/shop page. */
    public function store(StartConversationRequest $request): ConversationResource|JsonResponse
    {
        $owner = SellerProfile::query()->find($request->integer('seller_id'))?->user;
        if ($owner !== null && $this->blocks->eitherBlocked($request->user(), $owner)) {
            return BlockList::forbidden("You can't send messages to this account.");
        }

        $conversation = Conversation::query()->firstOrCreate(
            [
                'buyer_id' => $request->user()->id,
                'seller_id' => $request->integer('seller_id'),
                'product_id' => $request->integer('product_id') ?: null,
            ],
            ['last_message_at' => now()],
        );

        return new ConversationResource($conversation->load(['buyer', 'seller', 'product', 'messages']));
    }

    /** Refreshes the caller's typing signal (CLAUDE.md feature 5) — see Conversation::markTyping. */
    public function typing(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);
        $conversation->markTyping($request->user());

        return new ConversationResource($conversation->load(['buyer', 'seller', 'product', 'messages']));
    }
}

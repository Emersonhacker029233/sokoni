<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $sellerId = $user->sellerProfileId();

        $conversations = Conversation::query()
            ->where(function ($q) use ($user, $sellerId) {
                $q->where('buyer_id', $user->id);
                if ($sellerId) {
                    $q->orWhere('seller_id', $sellerId);
                }
            })
            ->with(['buyer', 'seller', 'product', 'messages'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);
        $conversation->load(['buyer', 'seller', 'product', 'messages']);

        return new ConversationResource($conversation);
    }

    /** Pinned per-product thread context (CLAUDE.md feature 5): buyer starts (or resumes) a thread from a product/shop page. */
    public function store(StartConversationRequest $request): ConversationResource
    {
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
}

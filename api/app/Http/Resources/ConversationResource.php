<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastMessage = $this->messages->last();

        return [
            'id' => $this->id,
            'buyer' => $this->whenLoaded('buyer', fn () => $this->buyerFor($request)),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'order_id' => $this->order_id,
            'last_message' => $lastMessage?->body,
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->when(
                $request->user() !== null,
                fn () => $this->messages->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->count()
            ),
            'other_party_typing' => $this->when(
                $request->user() !== null,
                fn () => $this->otherPartyTyping($request->user())
            ),
        ];
    }

    /** A thread about a dispatch order hides the buyer's contact details from the seller, like the order itself (OrderResource). */
    private function buyerFor(Request $request): ?array
    {
        if ($this->buyer === null) {
            return null;
        }
        $viewerIsBuyer = $request->user()?->id === $this->buyer_id;
        if (! $viewerIsBuyer && $this->order_id !== null && $this->order?->isDispatch()) {
            return UserResource::withoutContact($this->buyer, $request);
        }

        return (new UserResource($this->buyer))->resolve($request);
    }
}

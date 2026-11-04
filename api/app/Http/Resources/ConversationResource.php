<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Messages a moderator hid never show up as the preview or in the unread count.
        $messages = $this->messages->where('is_hidden', false);
        $lastMessage = $messages->last();

        return [
            'id' => $this->id,
            'buyer' => new UserResource($this->whenLoaded('buyer')),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'order_id' => $this->order_id,
            'last_message' => $lastMessage?->body,
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->when(
                $request->user() !== null,
                fn () => $messages->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->count()
            ),
            'other_party_typing' => $this->when(
                $request->user() !== null,
                fn () => $this->otherPartyTyping($request->user())
            ),
        ];
    }
}

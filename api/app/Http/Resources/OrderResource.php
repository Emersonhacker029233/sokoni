<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'delivery_method' => $this->delivery_method,
            // Bolt delivery orders only (docs/delivery/BOLT.md): 'dispatch',
            // with the details at GET /api/delivery/orders/{id}. Every other
            // order carries no such key, exactly as before delivery existed.
            'fulfilment' => $this->when($this->isDispatch(), 'dispatch'),
            'address' => $this->address,
            // Bolt delivery orders: the drop-off phone the driver calls.
            // Only the buyer ever gets it back; the seller and 1.0 never do.
            'delivery_phone' => $this->when($this->isDispatch() && $this->viewerIsBuyer($request), $this->delivery_phone),
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'cancelled_reason' => $this->cancelled_reason,
            'buyer' => $this->whenLoaded('buyer', fn () => $this->buyerFor($request)),
            'seller' => new SellerSummaryResource($this->whenLoaded('seller')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'conversation_id' => $this->when(
                $this->relationLoaded('conversation'),
                fn () => $this->conversation?->id
            ),
            'timeline' => $this->timeline(),
            'has_review' => $this->when($this->relationLoaded('review'), fn () => $this->review !== null),
            'created_at' => $this->created_at,
        ];
    }

    private function viewerIsBuyer(Request $request): bool
    {
        return $request->user() !== null && $request->user()->id === $this->buyer_id;
    }

    /**
     * On a dispatch order a Sokoni rider meets the buyer, not the seller,
     * so the seller gets the buyer's name but no way to contact them outside
     * Sokoni chat (docs/delivery/PLAN.md privacy rules). The shape is the
     * usual user object with the contact fields null, which Sokoni 1.0
     * already handles because phone and email are optional on every account.
     * Legacy orders are unchanged: the seller delivers and needs the phone.
     */
    private function buyerFor(Request $request): ?array
    {
        if ($this->buyer === null) {
            return null;
        }
        return $this->isDispatch() && ! $this->viewerIsBuyer($request)
            ? UserResource::withoutContact($this->buyer, $request)
            : (new UserResource($this->buyer))->resolve($request);
    }
}

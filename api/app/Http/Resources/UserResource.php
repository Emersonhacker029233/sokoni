<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'locale' => $this->locale,
            'is_seller' => $this->isSeller(),
            'seller_status' => $this->sellerProfile?->status,
            'seller_handle' => $this->sellerProfile?->handle,
            'account_intent' => $this->account_intent,
            'terms_accepted' => $this->terms_accepted_at !== null,
            'created_at' => $this->created_at,
        ];
    }
}

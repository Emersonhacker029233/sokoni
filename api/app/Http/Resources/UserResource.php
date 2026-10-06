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
            'username' => $this->username,
            // Part D (username/password rework) 2.6: true for any account
            // that hasn't set a password yet — an old account that's never
            // been prompted, or one that dismissed the prompt last time.
            // The client shows the one-time upgrade prompt whenever this is
            // true, on any screen that receives a user object, not just
            // right after signing in.
            'needs_credential_setup' => $this->needsCredentialSetup(),
            'two_factor_enabled' => $this->two_factor_enabled,
            'avatar' => $this->avatar,
            'locale' => $this->locale,
            'is_seller' => $this->isSeller(),
            'seller_status' => $this->sellerProfile?->status,
            'seller_handle' => $this->sellerProfile?->handle,
            'account_intent' => $this->account_intent,
            'terms_accepted' => $this->terms_accepted_at !== null,
            'created_at' => $this->created_at,
            // Part 4 (client feedback): Settings' Notifications section —
            // "marketing" is the pre-existing marketing_consent column
            // (collected at registration), not a new field.
            'notify_orders' => $this->notify_orders,
            'notify_messages' => $this->notify_messages,
            'notify_offers' => $this->notify_offers,
            'notify_marketing' => $this->marketing_consent,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What anyone may see about another user: name and avatar, never phone,
 * email or username. `is_seller` and `terms_accepted` stay because the
 * app's user model (iOS 1.0 (7) included) requires both keys to parse;
 * every other UserResource field is optional there.
 *
 * @mixin \App\Models\User
 */
class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'is_seller' => $this->isSeller(),
            'terms_accepted' => $this->terms_accepted_at !== null,
        ];
    }
}

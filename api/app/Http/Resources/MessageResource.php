<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'attachment' => $this->attachment,
            'is_mine' => $this->sender_id === $request->user()?->id,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}

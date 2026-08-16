<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppNotification */
class AppNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            // Named `payload`, not `data`: a resource whose own toArray()
            // output already contains a top-level `data` key defeats
            // Laravel's automatic response wrapping entirely (see
            // ResourceResponse::haveDefaultWrapperAndDataIsUnwrapped —
            // it checks `array_key_exists('data', $data)` on this exact
            // array before deciding whether to wrap), which silently
            // strips the `{"data": {...}}` envelope every other endpoint
            // in this API returns. The `app_notifications.data` DB column
            // itself keeps its name; only the API-facing key changes.
            'payload' => $this->data,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at,
        ];
    }
}

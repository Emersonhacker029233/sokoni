<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use App\Services\Delivery\DeliveryNotifier;
use Illuminate\Support\Collection;

/** Test double: records every delivery/return push instead of sending it, and writes the inbox row like the real one. */
class RecordingDeliveryNotifier implements DeliveryNotifier
{
    /** @var list<array{user_id: int, title: string, body: string, data: array}> */
    public array $sent = [];

    public function send(User $user, string $title, string $body, array $data = []): void
    {
        $user->appNotifications()->create(['title' => $title, 'body' => $body, 'data' => $data]);
        $this->sent[] = ['user_id' => $user->id, 'title' => $title, 'body' => $body, 'data' => $data];
    }

    public function to(User $user): Collection
    {
        return collect($this->sent)->where('user_id', $user->id)->values();
    }

    public function ofType(string $type): Collection
    {
        return collect($this->sent)->filter(fn ($p) => ($p['data']['type'] ?? null) === $type)->values();
    }
}

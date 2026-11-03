<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use App\Services\Delivery\DeliveryNotifier;
use Illuminate\Support\Collection;

/** Test double: records every delivery push instead of sending it. */
class RecordingDeliveryNotifier implements DeliveryNotifier
{
    /** @var list<array{user_id: int, app: string, title: string, body: string, data: array, silent: bool}> */
    public array $sent = [];

    /** Tests flip this to simulate real FCM being configured. */
    public bool $live = false;

    public function canReach(User $user, string $app): bool
    {
        if (! $this->live) {
            return false;
        }
        $devices = $user->devices();
        $app === DeliveryNotifier::APP_DRIVER
            ? $devices->where('app', DeliveryNotifier::APP_DRIVER)
            : $devices->where(fn ($q) => $q->whereNull('app')->orWhere('app', DeliveryNotifier::APP_CUSTOMER));

        return $devices->exists();
    }

    public function send(User $user, string $app, string $title, string $body, array $data = [], bool $silent = false): void
    {
        $this->sent[] = ['user_id' => $user->id, 'app' => $app, 'title' => $title, 'body' => $body, 'data' => $data, 'silent' => $silent];
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

<?php

namespace App\Services\Delivery;

use App\Models\User;
use Illuminate\Support\Collection;

abstract class BaseDeliveryNotifier implements DeliveryNotifier
{
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        // Delivery and return updates also land in the in-app inbox (the
        // same app_notifications table Sokoni already reads), so a missed
        // push is never a missed update.
        $user->appNotifications()->create(['title' => $title, 'body' => $body, 'data' => $data]);

        $tokens = $this->tokensFor($user);
        if ($tokens->isNotEmpty()) {
            $this->deliver($user, $tokens, $title, $body, $this->stringify($data));
        }
    }

    /**
     * The Sokoni app's devices. Rows registered by the retired Sokoni
     * Driver app (devices.app = 'driver', staging only) are skipped.
     *
     * @return Collection<int, string>
     */
    protected function tokensFor(User $user): Collection
    {
        return $user->devices()
            ->where(fn ($q) => $q->whereNull('app')->orWhere('app', '!=', 'driver'))
            ->pluck('fcm_token');
    }

    /** FCM data values must be strings. */
    private function stringify(array $data): array
    {
        return collect($data)
            ->reject(fn ($v) => $v === null)
            ->map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (string) $v)
            ->all();
    }

    /** @param Collection<int, string> $tokens */
    abstract protected function deliver(User $user, Collection $tokens, string $title, string $body, array $data): void;
}

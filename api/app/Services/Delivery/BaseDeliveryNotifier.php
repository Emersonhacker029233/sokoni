<?php

namespace App\Services\Delivery;

use App\Models\User;
use Illuminate\Support\Collection;

abstract class BaseDeliveryNotifier implements DeliveryNotifier
{
    public function send(User $user, string $app, string $title, string $body, array $data = [], bool $silent = false): void
    {
        // Customer-app delivery updates also land in the in-app inbox (the
        // same app_notifications table Sokoni already reads), so a missed
        // push is never a missed update. Silent pings are not inbox-worthy.
        if ($app === self::APP_CUSTOMER && ! $silent) {
            $user->appNotifications()->create(['title' => $title, 'body' => $body, 'data' => $data]);
        }

        $tokens = $this->tokensFor($user, $app);
        if ($tokens->isNotEmpty()) {
            $this->deliver($user, $tokens, $title, $body, $this->stringify($data), $silent);
        }
    }

    public function canReach(User $user, string $app): bool
    {
        return $this->isLive() && $this->tokensFor($user, $app)->isNotEmpty();
    }

    /** False for the logging stand-in: nothing actually leaves the server. */
    abstract protected function isLive(): bool;

    /** @return Collection<int, string> */
    protected function tokensFor(User $user, string $app): Collection
    {
        $query = $user->devices();
        if ($app === self::APP_DRIVER) {
            $query->where('app', self::APP_DRIVER);
        } else {
            $query->where(fn ($q) => $q->whereNull('app')->orWhere('app', self::APP_CUSTOMER));
        }

        return $query->pluck('fcm_token');
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
    abstract protected function deliver(User $user, Collection $tokens, string $title, string $body, array $data, bool $silent): void;
}

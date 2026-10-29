<?php

namespace App\Services\Delivery;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** MOCK: used when no Firebase service-account file is present (local dev, tests, until credentials arrive). */
class LogDeliveryNotifier extends BaseDeliveryNotifier
{
    protected function deliver(User $user, Collection $tokens, string $title, string $body, array $data, bool $silent): void
    {
        Log::info("[MOCK DELIVERY PUSH] user #{$user->id} ({$tokens->count()} device(s)): {$title} - {$body}", $data);
    }
}

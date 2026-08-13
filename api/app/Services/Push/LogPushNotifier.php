<?php

namespace App\Services\Push;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * MOCK: no Firebase project is configured yet (see BLOCKERS.md item 1).
 * Logs what would have been pushed via FCM to every one of $user's
 * registered devices instead of actually sending. Swap this binding in
 * AppServiceProvider for a kreait/laravel-firebase-backed implementation
 * once `FIREBASE_CREDENTIALS` is set.
 */
class LogPushNotifier implements PushNotifier
{
    public function notify(User $user, string $title, string $body, array $data = []): void
    {
        $tokenCount = $user->devices()->count();
        Log::info("[MOCK PUSH] to user #{$user->id} ({$tokenCount} device(s)): {$title} — {$body}", $data);
    }
}

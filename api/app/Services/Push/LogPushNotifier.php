<?php

namespace App\Services\Push;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * MOCK (the push half): no Firebase project is configured yet (see
 * BLOCKERS.md item 1). Logs what would have been pushed via FCM to every
 * one of $user's registered devices instead of actually sending.
 *
 * The persistence half is real, not mocked: every notification is also
 * written to `app_notifications`, which is what the app's Notifications
 * icon (CLAUDE.md Part 3) actually reads — a push arriving while the app
 * is closed is inherently best-effort, but the in-app inbox must have a
 * durable record regardless of whether the FCM send itself is real yet.
 * If a real Firebase-backed notifier replaces this one later, that
 * persistence call needs to move with it (or into a shared decorator) so
 * the inbox keeps working — this is the only bound `PushNotifier`
 * implementation today (see AppServiceProvider), so there's nowhere else
 * for it to live yet.
 */
class LogPushNotifier implements PushNotifier
{
    public function notify(User $user, string $title, string $body, array $data = []): void
    {
        $tokenCount = $user->devices()->count();
        Log::info("[MOCK PUSH] to user #{$user->id} ({$tokenCount} device(s)): {$title} — {$body}", $data);

        $user->appNotifications()->create(['title' => $title, 'body' => $body, 'data' => $data]);
    }
}

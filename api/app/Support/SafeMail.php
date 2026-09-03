<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every transactional email in this app is sent through here, never via a
 * bare `$notifiable->notify()` call — mail delivery must never fail the
 * user's underlying action (placing an order, leaving a review, saving
 * profile settings), and this host's queue has no worker running it
 * (`QUEUE_CONNECTION=database` with nothing consuming it — see
 * docs/DEPLOY.md), so sends happen synchronously and any failure is caught
 * and logged to a dedicated channel instead of bubbling up.
 */
class SafeMail
{
    public static function send(mixed $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            Log::channel('mail')->error('Failed to send notification', [
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

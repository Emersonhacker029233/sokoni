<?php

namespace App\Services\Delivery;

use App\Models\User;

/**
 * Push + inbox for delivery and return events, to the Sokoni app.
 * Separate from App\Services\Push\PushNotifier on purpose: that one
 * (LogPushNotifier) serves the existing order notifications and must not
 * change behaviour.
 */
interface DeliveryNotifier
{
    /**
     * Writes an inbox entry (app_notifications) and, when Firebase is
     * configured, pushes to the user's Sokoni app devices.
     *
     * @param  array<string, scalar|null>  $data  delivered as FCM data (values stringified)
     */
    public function send(User $user, string $title, string $body, array $data = []): void;
}

<?php

namespace App\Services\Delivery;

use App\Models\User;

/**
 * Push for delivery events only. Separate from App\Services\Push\PushNotifier
 * on purpose: that one (LogPushNotifier) serves the live Sokoni 1.0 order
 * notifications and must not change behaviour.
 */
interface DeliveryNotifier
{
    public const APP_CUSTOMER = 'sokoni';

    public const APP_DRIVER = 'driver';

    /**
     * @param  string  $app  which installed app should receive it: 'sokoni' or 'driver'
     * @param  array<string, scalar|null>  $data  delivered as FCM data (values stringified)
     * @param  bool  $silent  data-only, no visible notification (e.g. offer withdrawn)
     */
    public function send(User $user, string $app, string $title, string $body, array $data = [], bool $silent = false): void;
}

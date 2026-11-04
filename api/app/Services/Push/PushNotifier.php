<?php

namespace App\Services\Push;

use App\Models\User;

interface PushNotifier
{
    /**
     * `$actor` is the person who caused the notification (a message
     * sender, a shop posting an update). When `$user` has blocked them,
     * nothing is sent or stored (Apple Guideline 1.2).
     */
    public function notify(User $user, string $title, string $body, array $data = [], ?User $actor = null): void;
}

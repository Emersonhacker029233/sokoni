<?php

namespace App\Services\Push;

use App\Models\User;

interface PushNotifier
{
    public function notify(User $user, string $title, string $body, array $data = []): void;
}

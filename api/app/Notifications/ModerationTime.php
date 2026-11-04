<?php

namespace App\Notifications;

use Illuminate\Support\Carbon;

/** Moderation emails show times in Tanzania (EAT), not the server's UTC. */
final class ModerationTime
{
    public static function format(Carbon $time): string
    {
        return $time->copy()->timezone('Africa/Dar_es_Salaam')->format('D j M Y, H:i').' EAT';
    }
}

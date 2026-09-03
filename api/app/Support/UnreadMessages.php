<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;

/** Backs the mobile bottom nav's unread badge on Chats — a plain count, not a live/pushed value, matching this app's polling-based chat elsewhere. */
class UnreadMessages
{
    public static function countFor(User $user): int
    {
        return Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->whereHas('conversation', fn ($q) => $q
                ->where('buyer_id', $user->id)
                ->orWhereHas('seller', fn ($q2) => $q2->where('user_id', $user->id)))
            ->count();
    }
}

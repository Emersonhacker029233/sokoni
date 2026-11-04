<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Services\Moderation\BlockList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request, BlockList $blocks): AnonymousResourceCollection
    {
        // Notifications caused by someone the user later blocked drop out
        // too. Ones with no actor (system notices, and everything written
        // before actor_id existed) always stay.
        $blocked = $blocks->blockedUserIds($request->user());

        $notifications = $request->user()->appNotifications()
            ->when($blocked !== [], fn ($q) => $q->where(fn ($q) => $q->whereNull('actor_id')->orWhereNotIn('actor_id', $blocked)))
            ->latest()
            ->paginate(20);

        return AppNotificationResource::collection($notifications);
    }

    public function markRead(Request $request, \App\Models\AppNotification $notification): AppNotificationResource
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        // forceFill: `read_at` is deliberately excluded from Fillable (a
        // user should never mass-assign it via some other write path) —
        // same reasoning, and same silent-no-op trap, as every other
        // server-controlled status transition in this codebase.
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return new AppNotificationResource($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        // A bulk query builder update, not `forceFill()`+`save()` per-row —
        // fillable/guarded protection is a mass-assignment concern for
        // attribute arrays on a single model, it doesn't apply here at all.
        $request->user()->appNotifications()->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked read.']);
    }
}

<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * B3 (tester feedback): "Neither buyers nor sellers get notified of a new
 * order or a new message" on the website. The underlying data was already
 * real — `PushNotifier`/`LogPushNotifier::notify()` writes a genuine
 * `app_notifications` row on order placement, order status changes, and
 * every new message (both the API's and the website's own
 * `MessagesController::store()`) — the website simply had nowhere to
 * show any of it: no bell, no unread count, no list. This is that
 * surface, mirroring `Api\NotificationController`'s exact shape (same
 * model, same unread scope) over session auth instead of a Sanctum token,
 * the same reasoning `MessagesController`'s own docblock gives for why a
 * website equivalent exists at all alongside the API one.
 */
class NotificationsController extends Controller
{
    /** Polled by the header bell (see Alpine.data('notificationBell') in app.js) — same 20s cadence as messageNotifier. */
    public function unreadCount(): JsonResponse
    {
        return response()->json(['count' => Auth::user()->appNotifications()->unread()->count()]);
    }

    /** The bell dropdown's contents — the most recent notifications, not the full paginated history (see index() for that). */
    public function recent(): JsonResponse
    {
        $notifications = Auth::user()->appNotifications()->latest()->limit(8)->get();

        return response()->json([
            'notifications' => $notifications->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function index(): View
    {
        $notifications = Auth::user()->appNotifications()->latest()->paginate(20);

        return view('web.account.notifications', ['notifications' => $notifications, 'title' => __('site.account_notifications')]);
    }

    public function markRead(AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === Auth::id(), 403);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['message' => 'Marked read.']);
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()->appNotifications()->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked read.']);
    }
}

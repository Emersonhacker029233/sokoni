<?php

namespace App\Services\Moderation;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who a viewer has blocked (Apple Guideline 1.2: "instantly remove the
 * blocked user's content from the blocker's feed"). Bound as a scoped
 * singleton, so one request asks the database once per viewer however
 * many lists it filters.
 *
 * Direction matters:
 * - Content: only the blocker stops seeing the blocked user's posts.
 * - Contact: blocked either way stops messages, new conversations and
 *   orders between the two. The blocked user is never told why.
 */
class BlockList
{
    /** @var array<int, list<int>> */
    private array $blockedByViewer = [];

    /**
     * The signed-in user on any route, public ones included. Public API
     * routes have no `auth:sanctum` middleware, so `$request->user()`
     * asks the session guard and never sees the app's bearer token.
     */
    public static function viewer(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    /** @return list<int> user ids the viewer has blocked */
    public function blockedUserIds(?User $viewer): array
    {
        if ($viewer === null) {
            return [];
        }

        return $this->blockedByViewer[$viewer->id] ??= UserBlock::query()
            ->where('blocker_id', $viewer->id)
            ->pluck('blocked_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasBlocked(?User $viewer, ?int $userId): bool
    {
        return $userId !== null && in_array($userId, $this->blockedUserIds($viewer), true);
    }

    /** True when either user has blocked the other: no messages, conversations or orders between them. */
    public function eitherBlocked(User $a, User $b): bool
    {
        return UserBlock::query()
            ->where(fn ($q) => $q->where('blocker_id', $a->id)->where('blocked_id', $b->id))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b->id)->where('blocked_id', $a->id))
            ->exists();
    }

    public function forget(User $viewer): void
    {
        unset($this->blockedByViewer[$viewer->id]);
    }

    /**
     * The 403 every "you blocked them" or "you can't contact them" answer
     * uses. `code` lets the app show its blocked state (with Unblock when
     * the viewer is the blocker) instead of a generic error.
     */
    public static function forbidden(string $message, ?int $blockedUserId = null): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $message,
            'code' => 'blocked',
            'blocked_user_id' => $blockedUserId,
        ], fn ($value) => $value !== null), 403);
    }
}

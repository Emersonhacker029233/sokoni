<?php

namespace App\Models\Concerns;

use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Moderation\BlockList;
use Illuminate\Database\Eloquent\Builder;

/**
 * `->notBlockedBy($viewer)` on every kind of user-posted content: leaves
 * out whatever was written by someone the viewer blocked. A guest (null)
 * and a viewer who blocked nobody get the query back untouched.
 */
trait HidesBlockedAuthors
{
    public function scopeNotBlockedBy(Builder $query, ?User $viewer): Builder
    {
        $blocked = app(BlockList::class)->blockedUserIds($viewer);

        return $blocked === [] ? $query : $this->withoutAuthors($query, $blocked);
    }

    /** @param  list<int>  $userIds */
    abstract protected function withoutAuthors(Builder $query, array $userIds): Builder;

    /**
     * For content that belongs to a shop: leaves out the shops those users
     * own (deleted shops too, so nothing reappears if one is restored).
     *
     * @param  list<int>  $userIds
     */
    protected function withoutShopsOwnedBy(Builder $query, array $userIds): Builder
    {
        return $query->whereNotIn(
            $this->qualifyColumn('seller_id'),
            SellerProfile::withTrashed()->whereIn('user_id', $userIds)->select('id'),
        );
    }
}

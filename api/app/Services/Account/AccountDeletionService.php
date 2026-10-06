<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Self-service account deletion (Apple Guideline 5.1.1(v): "delete the
 * account, not merely deactivate it" — account creation happens in-app, so
 * deletion must too, not by email, not through a website). Deliberately a
 * separate, harsher path from the admin panel's moderation-oriented soft
 * delete (`UsersTable::cascadeDelete()`), which stays fully recoverable on
 * purpose, since it exists to undo a mistaken ban/suspension, not to honor
 * a legitimate user's own request to be gone. A user deleting their own
 * account gets: every session revoked immediately, every registered push
 * device forgotten, their shop and products removed from public view
 * (soft-deleted — same visibility mechanism the admin path uses, so they
 * disappear from the feed/search/shop pages at once), their own
 * personally-identifying fields overwritten rather than merely flagged,
 * and the user row itself soft-deleted on top of that so it stops
 * resolving anywhere a normal query runs. "Soft" here only means
 * recoverable by a database operator in a genuine emergency — not
 * meaningfully different from gone for every practical purpose, and it
 * also frees the phone number/handle for a future, unrelated signup
 * rather than leaving a dead unique-constraint row behind forever.
 *
 * Orders/order_items are deliberately never touched, for the same reason
 * the admin path doesn't touch them: an order is a two-party record, and
 * erasing it would corrupt the OTHER party's own legitimate order
 * history. The buyer/seller name on an old order reads as "Deleted user"
 * from here on — the intended, honest outcome, not a bug.
 */
class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $shop = $user->sellerProfile;
            if ($shop !== null) {
                $shop->products()->get()->each->delete();
                $shop->delete();
            }

            if ($user->avatar !== null) {
                $relative = str($user->avatar)->after(Storage::disk('public')->url(''));
                Storage::disk('public')->delete($relative);
            }

            $user->tokens()->delete();
            $user->devices()->delete();

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => null,
                'phone' => 'deleted-'.$user->id.'-'.Str::random(10),
                'avatar' => null,
                'provider_id' => null,
                'fcm_token' => null,
            ])->save();

            $user->delete();
        });
    }
}

<?php

namespace App\Services;

use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\SellerVerificationNotification;
use App\Services\Push\PushNotifier;
use App\Support\ActivityLogger;
use App\Support\SafeMail;

/**
 * D2 (tester feedback): the verify/reject decision, its activity-log
 * entry, and the seller's push+email notification were duplicated
 * near-identically in two places — SellerProfileResource's own header
 * actions (used when deep-linking into a specific seller's full record)
 * and SellerVerificationQueue (the actual main-nav queue every reviewer
 * uses day to day). Only the former ever grew the B4 notification
 * pairing; the queue page silently never got it, so "reject requires a
 * reason sent to the seller" was true for the reason but not the "sent"
 * part on the one page reviewers actually use. One shared place now, so
 * a third near-duplicate can't drift the same way again.
 */
class SellerVerificationService
{
    public static function verify(SellerProfile $seller, User $actor): void
    {
        $seller->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();
        ActivityLogger::record($actor, 'seller.verified', $seller);

        app(PushNotifier::class)->notify(
            $seller->user,
            'Shop verified',
            "Your shop \"{$seller->shop_name}\" is verified and now visible on Sokoni.",
            ['seller_id' => $seller->id],
        );
        if ($seller->user->email !== null) {
            SafeMail::send($seller->user, new SellerVerificationNotification($seller));
        }
    }

    public static function reject(SellerProfile $seller, User $actor, string $reason): void
    {
        $seller->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'verified_at' => null,
        ])->save();
        ActivityLogger::record($actor, 'seller.rejected', $seller, $reason);

        app(PushNotifier::class)->notify(
            $seller->user,
            'Shop registration update',
            "Your shop \"{$seller->shop_name}\" registration wasn't approved: {$reason}",
            ['seller_id' => $seller->id],
        );
        if ($seller->user->email !== null) {
            SafeMail::send($seller->user, new SellerVerificationNotification($seller));
        }
    }
}

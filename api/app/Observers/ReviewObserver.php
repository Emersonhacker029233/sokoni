<?php

namespace App\Observers;

use App\Models\Review;
use App\Notifications\ReviewReceivedNotification;
use App\Services\Push\PushNotifier;
use App\Support\SafeMail;

/** Keeps SellerProfile.rating_avg / rating_count denormalised off the reviews table. */
class ReviewObserver
{
    public function __construct(private readonly PushNotifier $push) {}

    public function created(Review $review): void
    {
        $this->recalculate($review);

        $seller = $review->seller?->user;
        if (! $seller) {
            return;
        }

        // A5 (tester feedback): this was email-only — most sellers have no
        // email on file (it's optional, see C5), so in practice almost
        // none of them were ever actually notified. Orders already notify
        // both ways (OrderController::store()/updateStatus()); reviews now
        // match that, push first since it reaches the app immediately
        // regardless of whether an email exists at all.
        $this->push->notify(
            $seller,
            'New review',
            "You received a {$review->rating}-star review.",
            ['review_id' => $review->id],
        );

        if ($seller->email !== null) {
            SafeMail::send($seller, new ReviewReceivedNotification($review));
        }
    }

    public function updated(Review $review): void
    {
        // is_hidden: an admin hiding an abusive review must take it out of
        // the seller's public rating immediately, not just off the public
        // review list (CLAUDE.md admin rebuild, Section 4).
        if ($review->wasChanged('rating') || $review->wasChanged('is_hidden')) {
            $this->recalculate($review);
        }
    }

    public function deleted(Review $review): void
    {
        $this->recalculate($review);
    }

    private function recalculate(Review $review): void
    {
        $seller = $review->seller;
        if (! $seller) {
            return;
        }

        $stats = $seller->reviews()->where('is_hidden', false)->selectRaw('avg(rating) as avg_rating, count(*) as total')->first();

        $seller->forceFill([
            'rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'rating_count' => (int) ($stats->total ?? 0),
        ])->save();
    }
}

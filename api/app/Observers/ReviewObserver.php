<?php

namespace App\Observers;

use App\Models\Review;

/** Keeps SellerProfile.rating_avg / rating_count denormalised off the reviews table. */
class ReviewObserver
{
    public function created(Review $review): void
    {
        $this->recalculate($review);
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

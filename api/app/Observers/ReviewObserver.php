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
        if ($review->wasChanged('rating')) {
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

        $stats = $seller->reviews()->selectRaw('avg(rating) as avg_rating, count(*) as total')->first();

        $seller->forceFill([
            'rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'rating_count' => (int) ($stats->total ?? 0),
        ])->save();
    }
}

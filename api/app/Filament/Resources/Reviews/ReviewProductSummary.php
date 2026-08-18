<?php

namespace App\Filament\Resources\Reviews;

use App\Models\Review;

/**
 * Reviews are per completed *order*, not per product (CLAUDE.md's own
 * data model — an order can hold several products, all from one seller,
 * and a review is of the seller experience, not a single item), so
 * there's no clean 1:1 "the product" this review is about. Rather than
 * inventing a field the schema doesn't have, this surfaces the order's
 * actual item(s) honestly — used by both the table and the detail page.
 */
class ReviewProductSummary
{
    public static function for(Review $review): string
    {
        $items = $review->order?->items ?? collect();
        if ($items->isEmpty()) {
            return '—';
        }

        $first = $items->first()->title_snapshot;
        $remaining = $items->count() - 1;

        return $remaining > 0 ? "{$first} +{$remaining} more" : $first;
    }
}

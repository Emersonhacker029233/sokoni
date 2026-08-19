<?php

namespace App\Services\Seller;

use App\Models\Order;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\DB;

/**
 * Extracted from `Api\SellerDashboardController` so the website's own
 * shop dashboard (CLAUDE.md website Section 6: "Sellers additionally get
 * their shop dashboard") reads the exact same real numbers the app's My
 * Shop screen does, not a second computation.
 */
class SellerDashboardStats
{
    /** @return array{total_views: int, saves_last_30_days: int, orders_last_30_days: int} */
    public function forSeller(SellerProfile $seller): array
    {
        $since = now()->subDays(30);

        return [
            // Lifetime, not 30-day — `products.views` is a single
            // incrementing counter with no per-event timestamp log behind
            // it, so there's no real data to scope it to a window. See
            // Api\SellerDashboardController's own docblock for the same call.
            'total_views' => (int) $seller->products()->sum('views'),
            'saves_last_30_days' => DB::table('favorites')
                ->whereIn('product_id', $seller->products()->pluck('id'))
                ->where('created_at', '>=', $since)
                ->count(),
            'orders_last_30_days' => Order::query()
                ->where('seller_id', $seller->id)
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }
}

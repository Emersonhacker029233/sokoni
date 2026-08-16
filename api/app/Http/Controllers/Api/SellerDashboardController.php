<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SellerDashboardController extends Controller
{
    /**
     * Owner-only stats strip on the shop profile (CLAUDE.md Part 4:
     * "views, saves and orders in the last 30 days, pulled from real
     * data"). `views` is honestly reported as a lifetime total, not a
     * 30-day figure — `products.views` is a single incrementing counter
     * with no per-event timestamp log behind it (see
     * `ProductController::show`), so there's no real data to scope it to
     * a window; fabricating a 30-day-looking number from a lifetime
     * counter would be exactly the kind of placeholder CLAUDE.md rules
     * out. Saves and orders both have real timestamped rows to scope.
     */
    public function show(SellerProfile $seller): JsonResponse
    {
        $this->authorize('update', $seller);

        $since = now()->subDays(30);

        return response()->json([
            'data' => [
                'total_views' => (int) $seller->products()->sum('views'),
                'saves_last_30_days' => DB::table('favorites')
                    ->whereIn('product_id', $seller->products()->pluck('id'))
                    ->where('created_at', '>=', $since)
                    ->count(),
                'orders_last_30_days' => Order::query()
                    ->where('seller_id', $seller->id)
                    ->where('created_at', '>=', $since)
                    ->count(),
            ],
        ]);
    }
}

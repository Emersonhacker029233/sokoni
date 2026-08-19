<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Services\Seller\SellerDashboardStats;
use Illuminate\Http\JsonResponse;

class SellerDashboardController extends Controller
{
    /**
     * Owner-only stats strip on the shop profile (CLAUDE.md Part 4:
     * "views, saves and orders in the last 30 days, pulled from real
     * data") — see SellerDashboardStats for exactly what's real vs.
     * lifetime and why.
     */
    public function show(SellerProfile $seller, SellerDashboardStats $stats): JsonResponse
    {
        $this->authorize('update', $seller);

        return response()->json(['data' => $stats->forSeller($seller)]);
    }
}

<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerDashboardStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShopDashboardController extends Controller
{
    public function __invoke(SellerDashboardStats $stats): View|RedirectResponse
    {
        $seller = Auth::user()->sellerProfile()->with('category')->first();

        if (! $seller) {
            return redirect()->route('web.account.shop.register');
        }

        return view('web.account.shop-dashboard', [
            'seller' => $seller,
            'stats' => $stats->forSeller($seller),
            'recentOrders' => $seller->orders()->with('buyer')->latest()->limit(5)->get(),
            'productsCount' => $seller->products()->count(),
            'title' => __('site.account_shop'),
        ]);
    }
}

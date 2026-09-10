<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __invoke(Request $request, string $handle): View
    {
        $seller = SellerProfile::query()
            ->where('handle', $handle)
            ->where('status', 'verified')
            ->with('category')
            ->firstOrFail();

        $tab = $request->string('tab')->toString() ?: 'listings';

        $products = Product::query()
            ->visible()
            ->forSeller($seller->id)
            ->with('media')
            ->latest()
            ->paginate(24, pageName: 'page')
            ->withQueryString();

        $showcases = $tab === 'gallery' ? Showcase::query()->where('seller_id', $seller->id)->latest()->get() : collect();
        $updates = $tab === 'gallery' ? Update::query()->where('seller_id', $seller->id)->active()->latest()->get() : collect();

        $reviews = $tab === 'reviews'
            ? $seller->reviews()->where('is_hidden', false)->with('buyer')->latest()->paginate(20)->withQueryString()
            : null;

        $distribution = $seller->reviews()->where('is_hidden', false)->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');

        // A1 (tester feedback): the shop page's Reviews tab had no way to
        // leave a review at all, and no explanation either way — a buyer
        // with a qualifying order saw the same blank tab as one without,
        // both reading as "broken" rather than "not eligible yet". The
        // actual review form still lives on the order page (it needs a
        // specific $order to bind to — see OrdersController::storeReview()
        // / StoreReviewRequest), so this only finds which state to show:
        // a direct link to that order, or the honest "not yet" copy.
        $reviewableOrder = null;
        if ($tab === 'reviews' && Auth::guard('web')->check()) {
            $reviewableOrder = Order::query()
                ->where('buyer_id', Auth::id())
                ->where('seller_id', $seller->id)
                ->where('status', 'completed')
                ->whereDoesntHave('review')
                ->latest()
                ->first();
        }

        return view('web.shop', [
            'seller' => $seller,
            'tab' => $tab,
            'products' => $products,
            'showcases' => $showcases,
            'updates' => $updates,
            'reviews' => $reviews,
            'reviewableOrder' => $reviewableOrder,
            'distribution' => $distribution,
            'openingHours' => \App\Support\OpeningHours::parse($seller->opening_hours),
            'title' => $seller->shop_name,
            'description' => $seller->bio ?: "{$seller->shop_name} on Sokoni — {$seller->category?->name_en}, {$seller->district}, {$seller->region}.",
            'ogType' => 'business.business',
            'ogImage' => $seller->logo,
        ]);
    }
}

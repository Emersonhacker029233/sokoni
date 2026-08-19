<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use Illuminate\Http\Request;
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

        return view('web.shop', [
            'seller' => $seller,
            'tab' => $tab,
            'products' => $products,
            'showcases' => $showcases,
            'updates' => $updates,
            'reviews' => $reviews,
            'distribution' => $distribution,
            'openingHours' => \App\Support\OpeningHours::parse($seller->opening_hours),
            'title' => $seller->shop_name,
            'description' => $seller->bio ?: "{$seller->shop_name} on Sokoni — {$seller->category?->name_en}, {$seller->district}, {$seller->region}.",
            'ogType' => 'business.business',
            'ogImage' => $seller->logo,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrdersController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();

        return view('web.account.dashboard', [
            'title' => 'My account',
            'recentOrders' => $user->orders()->with('seller')->latest()->limit(5)->get(),
            'savedCount' => $user->favorites()->count(),
            'isSeller' => $user->isSeller(),
        ]);
    }

    public function index(Request $request): View
    {
        $orders = Auth::user()->orders()
            ->with(['seller', 'items'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('web.account.orders', ['orders' => $orders, 'title' => 'My orders']);
    }

    public function show(Order $order): View
    {
        abort_unless($order->buyer_id === Auth::id(), 404);

        $order->load(['seller', 'items', 'review']);

        return view('web.account.order-show', [
            'order' => $order,
            'timeline' => $order->timeline(),
            'title' => 'Order '.$order->code,
        ]);
    }

    /**
     * The website had no way to submit a review at all — only the app
     * did (tester feedback A3). Reuses the exact same `StoreReviewRequest`
     * the API uses (order-completed + not-already-reviewed + ownership,
     * the whole anti-fake-review mechanism, CLAUDE.md feature 2), so a
     * review left here is validated identically to one left in the app.
     * Redirects back to the same order page — the flash message and the
     * now-existing `$order->review` render immediately on that follow-up
     * GET, with no separate AJAX/JS needed to "avoid a manual refresh".
     */
    public function storeReview(StoreReviewRequest $request, Order $order): RedirectResponse
    {
        Review::query()->create([
            ...$request->validated(),
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'buyer_id' => $order->buyer_id,
        ]);

        return redirect()->route('web.account.orders.show', $order)->with('status', __('site.review_submitted'));
    }
}

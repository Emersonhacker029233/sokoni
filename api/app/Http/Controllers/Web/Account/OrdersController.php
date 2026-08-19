<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
}

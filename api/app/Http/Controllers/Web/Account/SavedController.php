<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SavedController extends Controller
{
    public function __invoke(): View
    {
        $products = Auth::user()->favorites()
            ->with(['seller', 'media', 'category'])
            ->paginate(24)
            ->withQueryString();

        return view('web.account.saved', ['products' => $products, 'title' => __('site.account_saved')]);
    }
}

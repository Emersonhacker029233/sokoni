<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOpeningHoursRequest;
use App\Models\SellerProfile;
use Illuminate\Http\RedirectResponse;

class ShopHoursController extends Controller
{
    public function update(UpdateOpeningHoursRequest $request, SellerProfile $seller): RedirectResponse
    {
        $seller->update(['opening_hours' => $request->normalizedHours()]);

        return back()->with('status', __('site.shop_hours_saved'));
    }
}

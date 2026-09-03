<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSellerLogoRequest;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * The web equivalent of Api\SellerProfileController::updateLogo() — sellers
 * had no way to change their shop logo from the website at all (tester
 * feedback A5). AJAX rather than a plain form field so the new logo can
 * reflect immediately on this same page without a full reload, matching
 * the product photo manager's own upload pattern; the client compresses
 * the image before it ever reaches this endpoint (see app.js), same
 * reasoning as every other upload on this 3G-target site.
 */
class ShopLogoController extends Controller
{
    public function update(UpdateSellerLogoRequest $request, SellerProfile $seller): JsonResponse
    {
        $url = Storage::disk('public')->url($request->file('logo')->store('sellers/logos', 'public'));
        $seller->update(['logo' => $url]);

        return response()->json(['logo' => $url]);
    }
}

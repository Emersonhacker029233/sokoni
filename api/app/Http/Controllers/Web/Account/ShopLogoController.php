<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSellerLogoRequest;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $previousLogo = $seller->logo;

        $url = Storage::disk('public')->url($request->file('logo')->store('sellers/logos', 'public'));
        $seller->update(['logo' => $url]);

        if ($previousLogo) {
            $relative = str($previousLogo)->after(Storage::disk('public')->url(''));
            Storage::disk('public')->delete($relative);
        }

        return response()->json(['logo' => $url]);
    }

    /**
     * Part 2 (client feedback): "the same applies anywhere else an image
     * can be uploaded but not removed — check ... seller logos." Only
     * "Change" existed — a seller who wanted to go back to the plain
     * initial-letter placeholder (`settings.blade.php`'s own `!logoUrl`
     * fallback) had no way to. Same owner-only check as [update],
     * without that action's `logo`-file validation rule (nothing to
     * validate on a delete).
     */
    public function destroy(Request $request, SellerProfile $seller): JsonResponse
    {
        $this->authorize('update', $seller);

        if ($seller->logo) {
            $relative = str($seller->logo)->after(Storage::disk('public')->url(''));
            Storage::disk('public')->delete($relative);
            $seller->update(['logo' => null]);
        }

        return response()->json(['logo' => null]);
    }
}

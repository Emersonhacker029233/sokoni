<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SellerWebRegistrationRequest;
use App\Models\Category;
use App\Services\Geo\NominatimGeocoder;
use App\Services\Nida\NidaVerifier;
use App\Support\TanzaniaRegions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The website's equivalent of the app's 3-step seller onboarding wizard
 * (B1/B2, tester feedback: the licence step is gone entirely, identity is
 * now just the NIDA number) — one scrollable form with the same sections
 * instead of a step-by-step flow, since a web page doesn't need the
 * wizard pattern a small phone screen does. Writes the exact same
 * `SellerProfile` fields the app's own `Api\SellerProfileController`
 * writes, in the same order, so a profile created here is
 * indistinguishable from one created through the app.
 */
class SellerRegistrationController extends Controller
{
    public function __construct(private readonly NidaVerifier $nidaVerifier) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::user()->isSeller()) {
            return redirect()->route('web.account.shop');
        }

        return view('web.account.seller-register', [
            // A6 (tester feedback): missing whereNull('parent_id') here —
            // this form pre-dates the subcategory taxonomy and was never
            // updated, so it showed all ~89 categories (13 top-level plus
            // every subcategory) flat and mixed, while the product form
            // (ShopProductsController::categoryFormData()) correctly shows
            // only the 13 top-level ones. A shop's own category has never
            // been meant to be a subcategory pick on any surface — matching
            // the same fix already applied to the app's three equivalent
            // pickers (home chips, seller onboarding, "Create an account").
            'categories' => Category::whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => TanzaniaRegions::options(),
            'title' => __('site.seller_register_title'),
        ]);
    }

    public function store(SellerWebRegistrationRequest $request, NominatimGeocoder $geocoder): RedirectResponse
    {
        $data = $request->validated();

        // The web form never sends lat/lng at all (no map pin), so
        // validated() — which only includes keys actually present in the
        // request — omits them entirely rather than returning null; the
        // app's own API, which always sends both, doesn't have this gap.
        $data['lat'] ??= null;
        $data['lng'] ??= null;

        // No map pin on the web form to supply these from — best-effort
        // server-side geocode of the address the seller just typed. Never
        // blocks: a failed/slow lookup just leaves lat/lng null, the same
        // "address only, no map" state the shop page already renders
        // correctly (see Part 2 of this same task).
        if (! $data['lat'] && ! $data['lng']) {
            $coordinates = $geocoder->geocode($data['address'], $data['district'], $data['region']);
            if ($coordinates) {
                $data['lat'] = $coordinates['lat'];
                $data['lng'] = $coordinates['lng'];
            }
        }

        $seller = Auth::user()->sellerProfile()->create([
            'shop_name' => $data['shop_name'],
            'handle' => $data['handle'],
            'category_id' => $data['category_id'],
            'bio' => $data['bio'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'address' => $data['address'],
            'region' => $data['region'],
            'district' => $data['district'],
            'nida_number' => $data['nida_number'],
        ]);

        // MOCK: see ManualReviewNidaVerifier — real verification is manual,
        // via the Filament seller queue. This just confirms receipt,
        // exactly as the app's own onboarding identity step does.
        $this->nidaVerifier->submit($data['nida_number']);

        return redirect()->route('web.account.shop')->with('status', __('site.seller_register_submitted'));
    }
}

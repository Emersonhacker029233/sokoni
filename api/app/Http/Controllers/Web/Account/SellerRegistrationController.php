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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The website's equivalent of the app's 4-step seller onboarding wizard —
 * one scrollable form with the same sections instead of a step-by-step
 * flow, since a web page doesn't need the wizard pattern a small phone
 * screen does. Writes the exact same `SellerProfile` fields the app's own
 * `Api\SellerProfileController` writes, in the same order, so a profile
 * created here is indistinguishable from one created through the app.
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
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
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

        $nidaImagePath = $request->file('nida_image')->store('sellers/nida', 'public');
        // Optional (NIDA-only verification, client request) — NIDA is the
        // actual basis of verification; the licence is an optional extra,
        // never required to submit for review.
        $licenceFilePath = $request->hasFile('licence_file')
            ? $request->file('licence_file')->store('sellers/licences', 'public')
            : null;

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
            'nida_image' => $nidaImagePath,
            'licence_file' => $licenceFilePath,
        ]);

        // MOCK: see ManualReviewNidaVerifier — real verification is manual,
        // via the Filament seller queue. This just confirms receipt,
        // exactly as the app's own onboarding step 3 does.
        $this->nidaVerifier->submit($data['nida_number'], $nidaImagePath);

        return redirect()->route('web.account.shop')->with('status', __('site.seller_register_submitted'));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerOnboardBusinessRequest;
use App\Http\Requests\SellerOnboardIdentityRequest;
use App\Http\Requests\SellerOnboardLicenceRequest;
use App\Http\Requests\SellerOnboardLocationRequest;
use App\Http\Requests\SellerProfileUpdateRequest;
use App\Http\Resources\SellerProfileResource;
use App\Models\SellerProfile;
use App\Services\Geo\DistanceQuery;
use App\Services\Nida\NidaVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SellerProfileController extends Controller
{
    public function __construct(private readonly NidaVerifier $nidaVerifier) {}

    /** Browse sellers — "New Sellers" (sort=newest) or nearby, verified only. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SellerProfile::query()->verified()->with('category');

        if ($request->filled('lat') && $request->filled('lng')) {
            // Reuses the same distance engine as product discovery.
            $distances = DistanceQuery::nearbySellerDistances(
                $request->float('lat'),
                $request->float('lng'),
                $request->float('radius_km') ?: null,
            );
            $sellers = $query->whereIn('id', array_keys($distances))->get()
                ->sortBy(fn ($seller) => $distances[$seller->id])
                ->values();
        } else {
            $sellers = $query->orderByDesc('verified_at')->limit(50)->get();
        }

        return SellerProfileResource::collection($sellers);
    }

    public function show(string $handle): SellerProfileResource
    {
        $seller = SellerProfile::query()->where('handle', $handle)->with('category')->firstOrFail();

        return new SellerProfileResource($seller);
    }

    /** Step 1: business details. Creates the profile in `pending` status. */
    public function store(SellerOnboardBusinessRequest $request): SellerProfileResource
    {
        $seller = $request->user()->sellerProfile()->create($request->validated());

        return new SellerProfileResource($seller->load('category'));
    }

    /** Step 2: map pin + confirmed address. */
    public function updateLocation(SellerOnboardLocationRequest $request, SellerProfile $seller): SellerProfileResource
    {
        $seller->update($request->validated());

        return new SellerProfileResource($seller->load('category'));
    }

    /** Step 3: NIDA number + ID photo. */
    public function updateIdentity(SellerOnboardIdentityRequest $request, SellerProfile $seller): SellerProfileResource
    {
        $path = $request->file('nida_image')->store('sellers/nida', 'public');
        $seller->update(['nida_number' => $request->string('nida_number'), 'nida_image' => $path]);

        // MOCK: see ManualReviewNidaVerifier — real verification is manual,
        // via the Filament seller queue. This just confirms receipt.
        $this->nidaVerifier->submit($request->string('nida_number'), $path);

        return new SellerProfileResource($seller->load('category'));
    }

    /** Step 4: business/trading licence. Submitting this puts the seller in the verification queue. */
    public function updateLicence(SellerOnboardLicenceRequest $request, SellerProfile $seller): SellerProfileResource
    {
        $path = $request->file('licence_file')->store('sellers/licences', 'public');
        $seller->update(['licence_file' => $path]);

        return new SellerProfileResource($seller->load('category'));
    }

    public function update(SellerProfileUpdateRequest $request, SellerProfile $seller): SellerProfileResource
    {
        $seller->update($request->validated());

        return new SellerProfileResource($seller->load('category'));
    }
}

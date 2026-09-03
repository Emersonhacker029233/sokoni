<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerOnboardBusinessRequest;
use App\Http\Requests\SellerOnboardIdentityRequest;
use App\Http\Requests\SellerOnboardLicenceRequest;
use App\Http\Requests\SellerOnboardLocationRequest;
use App\Http\Requests\SellerProfileUpdateRequest;
use App\Http\Requests\UpdateSellerLogoRequest;
use App\Http\Resources\SellerProfileResource;
use App\Models\SellerProfile;
use App\Services\Geo\DistanceQuery;
use App\Services\Nida\NidaVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Live "is this handle available" check for the account-creation
     * flow's details step (CLAUDE.md restructure, 2026-08-25: "validate as
     * you go, not at the end") — reuses the exact format/reserved-word
     * rules SellerOnboardBusinessRequest already enforces at submit time,
     * just without requiring an authenticated user or creating anything.
     */
    public function handleAvailability(Request $request): JsonResponse
    {
        $handle = (string) $request->query('handle', '');

        $available = preg_match(SellerProfile::HANDLE_PATTERN, $handle) === 1
            && ! in_array($handle, SellerProfile::RESERVED_HANDLES, true)
            && ! SellerProfile::query()->where('handle', $handle)->exists();

        return response()->json(['available' => $available]);
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

    /**
     * Step 4: business/trading licence — optional (NIDA-only verification,
     * client request). Step 3's NIDA submission is what actually queues a
     * seller for manual review (see updateIdentity() above); this step
     * just attaches an optional extra document if the seller has one, and
     * is safe to skip (no file, no-op) without blocking verification.
     */
    public function updateLicence(SellerOnboardLicenceRequest $request, SellerProfile $seller): SellerProfileResource
    {
        if ($request->hasFile('licence_file')) {
            $path = $request->file('licence_file')->store('sellers/licences', 'public');
            $seller->update(['licence_file' => $path]);
        }

        return new SellerProfileResource($seller->load('category'));
    }

    public function update(SellerProfileUpdateRequest $request, SellerProfile $seller): SellerProfileResource
    {
        $seller->update($request->validated());

        return new SellerProfileResource($seller->load('category'));
    }

    /**
     * Shop logo/avatar (CLAUDE.md Parts 3-4). Stored as a full public URL,
     * not a disk-relative path — unlike `nida_image`/`licence_file` (which
     * only ever a human reviewer opens directly, per `docs/DEPLOY.md`),
     * this one is rendered as an avatar image client-side, so it follows
     * the same URL convention `ProductMediaController` already uses.
     */
    public function updateLogo(UpdateSellerLogoRequest $request, SellerProfile $seller): SellerProfileResource
    {
        $url = Storage::disk('public')->url($request->file('logo')->store('sellers/logos', 'public'));
        $seller->update(['logo' => $url]);

        return new SellerProfileResource($seller->load('category'));
    }
}

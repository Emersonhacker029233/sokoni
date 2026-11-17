<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Moderation\BlockList;
use App\Support\DeliveryAccess;
use App\Support\Legal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/features: which dark-launched features this viewer may see.
 * The Sokoni app shows delivery and return screens only when the API says
 * so here (docs/delivery/BOLT.md, "Flags"). New route, so builds that
 * don't know it never call it; a 404 from an older server means "off".
 *
 * Public: a guest gets everything false.
 */
class FeatureController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = BlockList::viewer($request);
        $returns = DeliveryAccess::returnsEnabledFor($user);

        return response()->json(['data' => [
            'delivery_enabled' => DeliveryAccess::deliveryEnabledFor($user),
            'returns_enabled' => $returns,
            // Only while returns are on: the Return Policy section of the
            // Terms (1.2) the app asks the user to agree to once, kept
            // apart from terms_version (docs/delivery/RETURNS.md, "Terms").
            ...($returns ? [
                'returns_terms_version' => Legal::RETURNS_TERMS_VERSION,
                'returns_terms_accepted' => $user->returns_terms_version === Legal::RETURNS_TERMS_VERSION,
            ] : []),
        ]]);
    }
}

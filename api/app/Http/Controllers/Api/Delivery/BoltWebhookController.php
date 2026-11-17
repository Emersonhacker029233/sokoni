<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryJob;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltRideBooker;
use App\Services\Delivery\Bolt\BoltRideDetails;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/bolt: Bolt's ride state changes, for when Bolt turns
 * webhooks on for Sokoni's account (live only; the 10-second poll covers
 * everything until then and keeps running as the safety net).
 *
 * Bolt sends `Authorization: Bearer <BOLT_WEBHOOK_TOKEN>` from one of its
 * documented IPs, with `{ "ride_id": 1001, "type": "ON_DEMAND", "state": "..." }`.
 * The body is never trusted beyond "this ride changed": the full details
 * (driver, tracking link) are read back from Bolt; only if that fails is
 * the state from the body applied on its own.
 */
class BoltWebhookController extends Controller
{
    public function __invoke(Request $request, DeliveryJobService $jobs, BoltRideBooker $bolt, DeliveryStateMachine $stateMachine): JsonResponse
    {
        $expected = (string) config('delivery.bolt.webhook_token');
        abort_if($expected === '', 404);

        if (! hash_equals($expected, (string) $request->bearerToken())) {
            Log::warning('[bolt] webhook with a wrong or missing token', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $allowedIps = array_values(array_filter(array_map('trim', explode(',', (string) config('delivery.bolt.webhook_ips')))));
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            Log::warning('[bolt] webhook from an IP that is not Bolt\'s', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'ride_id' => ['required'],
            'type' => ['nullable', 'string', 'max:30'],
            'state' => ['required', 'string', 'max:40'],
        ]);

        $rideId = (string) $data['ride_id'];
        $type = strtoupper((string) ($data['type'] ?? 'ON_DEMAND'));
        if ($type !== 'ON_DEMAND') {
            return response()->json(['received' => true, 'handled' => false]);
        }

        $details = $this->details($bolt, $rideId) ?? new BoltRideDetails($rideId, strtoupper($data['state']));

        $job = DeliveryJob::query()->where('provider', DeliveryJob::PROVIDER_BOLT)->where('provider_ref', $rideId)->first();
        if ($job !== null) {
            $stateMachine->record($job, null, 'provider', 'provider_webhook', meta: ['state' => strtoupper($data['state'])]);
            $jobs->apply($job, $details);

            return response()->json(['received' => true, 'handled' => true]);
        }

        // Not ours (or already forgotten): answer 200 so Bolt doesn't keep retrying.
        Log::info("[bolt] webhook for unknown ride {$rideId}");

        return response()->json(['received' => true, 'handled' => false]);
    }

    private function details(BoltRideBooker $bolt, string $rideId): ?BoltRideDetails
    {
        try {
            return $bolt->client()->rideDetails($rideId);
        } catch (BoltApiException) {
            return null;
        }
    }
}

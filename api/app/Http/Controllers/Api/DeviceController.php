<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Register (or refresh) this device's FCM token for the signed-in
     * user. Part 5 (client feedback): "Push notification tokens must
     * follow the active account correctly, so notifications aren't
     * delivered to the wrong one." Looks up by `fcm_token` alone
     * (globally, not scoped to `$request->user()->devices()`) and
     * writes the CURRENT user onto whichever row it finds — a physical
     * device install re-registering after an account switch reassigns
     * the token to the newly active account rather than leaving the
     * previous account's row (same token) also in place, which would
     * have delivered every push to both accounts at once. See the
     * migration widening this table's unique constraint to `fcm_token`
     * alone for the same reasoning.
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        Device::query()->updateOrCreate(
            ['fcm_token' => $request->string('fcm_token')],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->string('platform'),
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['message' => 'Device registered.']);
    }
}

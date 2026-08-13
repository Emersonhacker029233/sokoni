<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /** Register (or refresh) this device's FCM token for the signed-in user. */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $request->user()->devices()->updateOrCreate(
            ['fcm_token' => $request->string('fcm_token')],
            ['platform' => $request->string('platform'), 'last_seen_at' => now()],
        );

        return response()->json(['message' => 'Device registered.']);
    }
}

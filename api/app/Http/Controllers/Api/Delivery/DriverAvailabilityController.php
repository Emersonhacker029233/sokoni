<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\DriverLocationRequest;
use App\Models\Device;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Services\Delivery\DeliveryActionException;
use App\Services\Delivery\DeliveryNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverAvailabilityController extends Controller
{
    /** PATCH /driver/availability {online, lat?, lng?} */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'online' => ['required', 'boolean'],
            'lat' => ['required_if:online,true,1', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_if:online,true,1', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        /** @var DriverProfile $driver */
        $driver = $request->attributes->get('driver');
        $online = (bool) $data['online'];

        if ($online) {
            if ($driver->activeVehicle()->whereNotNull('verified_at')->doesntExist()) {
                throw DeliveryActionException::make('vehicle_unverified', 403);
            }
            if ($driver->licence_expires_on !== null && $driver->licence_expires_on->isPast()) {
                throw DeliveryActionException::make('licence_expired', 403);
            }
            $driver->forceFill([
                'is_online' => true,
                'last_lat' => $data['lat'],
                'last_lng' => $data['lng'],
                'last_located_at' => now(),
            ])->save();
        } else {
            // Going offline never abandons a job in progress; it only stops new offers.
            $driver->forceFill(['is_online' => false])->save();
        }

        return response()->json(['data' => [
            'is_online' => $driver->is_online,
            'active_job_id' => $driver->activeJob()?->id,
        ]]);
    }

    /**
     * POST /driver/location. The ~30 s heartbeat while online (dispatch
     * reads it), and while on a job also a sparse breadcrumb kept 72 h
     * for disputes. The 4 s live stream goes to Firebase, not here.
     */
    public function location(DriverLocationRequest $request): JsonResponse
    {
        /** @var DriverProfile $driver */
        $driver = $request->attributes->get('driver');
        $job = $driver->activeJob();

        if (! $driver->is_online && $job === null) {
            // Location is only collected while online or on a job.
            return response()->json(['data' => ['accepted' => false, 'reason' => 'offline']]);
        }

        $driver->forceFill([
            'last_lat' => $request->float('lat'),
            'last_lng' => $request->float('lng'),
            'last_located_at' => now(),
        ])->save();

        $sampled = false;
        if ($job !== null) {
            $lastSample = DriverLocation::query()->where('delivery_job_id', $job->id)->max('recorded_at');
            if ($lastSample === null || now()->diffInSeconds($lastSample, true) >= (int) config('delivery.location_sample_seconds')) {
                DriverLocation::query()->create([
                    'driver_profile_id' => $driver->id,
                    'delivery_job_id' => $job->id,
                    'lat' => $request->float('lat'),
                    'lng' => $request->float('lng'),
                    'heading' => $request->filled('heading') ? (int) $request->input('heading') : null,
                    'speed' => $request->input('speed'),
                    'accuracy' => $request->input('accuracy'),
                    'recorded_at' => now(),
                ]);
                $sampled = true;
            }
        }

        return response()->json(['data' => ['accepted' => true, 'sampled' => $sampled, 'active_job_id' => $job?->id]]);
    }

    /** POST /driver/devices: FCM token of the Sokoni Driver app (kept apart from the customer app's tokens). */
    public function device(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);

        Device::query()->updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'app' => DeliveryNotifier::APP_DRIVER, 'last_seen_at' => now()],
        );

        return response()->json(['message' => 'Device registered.']);
    }
}

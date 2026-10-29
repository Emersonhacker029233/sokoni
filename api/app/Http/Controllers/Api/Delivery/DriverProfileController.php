<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\DriverDocumentsRequest;
use App\Http\Requests\Delivery\StoreDriverProfileRequest;
use App\Http\Requests\Delivery\UpsertVehicleRequest;
use App\Http\Resources\Delivery\DriverProfileResource;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryActionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Driver onboarding. Sign-in itself is the existing phone OTP / password
 * flow (/api/auth/*), so a driver is a normal Sokoni account with a phone.
 */
class DriverProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        return response()->json(['data' => $driver ? new DriverProfileResource($driver) : null]);
    }

    public function store(StoreDriverProfileRequest $request): DriverProfileResource
    {
        $user = $request->user();
        if ($user->phone === null) {
            throw DeliveryActionException::make('phone_required', 422);
        }

        $driver = $user->driverProfile ?? new DriverProfile(['user_id' => $user->id]);
        if ($driver->exists && $driver->isApproved()) {
            throw DeliveryActionException::make('already_approved', 409);
        }

        $driver->fill([
            'national_id_number' => $request->string('national_id_number')->toString(),
            'licence_number' => $request->input('licence_number'),
            'licence_expires_on' => $request->input('licence_expires_on'),
            'terms_accepted_at' => now(),
            'terms_version' => $request->string('terms_version')->toString(),
        ])->save();

        return new DriverProfileResource($driver->fresh());
    }

    public function documents(DriverDocumentsRequest $request): DriverProfileResource
    {
        $driver = $this->driverOrFail($request);
        if ($driver->hasActiveJob()) {
            throw DeliveryActionException::make('busy_on_job', 409);
        }

        $changes = [];
        foreach (['photo', 'national_id_image', 'licence_image'] as $field) {
            if ($request->hasFile($field)) {
                $old = $driver->{$field};
                $changes[$field] = $request->file($field)->store("drivers/{$driver->id}", 'local');
                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            }
        }

        // New identity documents on an approved driver need a fresh human
        // review; the driver goes offline until then.
        if ($driver->isApproved() && (isset($changes['national_id_image']) || isset($changes['licence_image']))) {
            $changes += ['status' => 'pending', 'is_online' => false, 'submitted_at' => now()];
        }

        $driver->forceFill($changes)->save();

        return new DriverProfileResource($driver->fresh());
    }

    public function vehicle(UpsertVehicleRequest $request): DriverProfileResource
    {
        $driver = $this->driverOrFail($request);
        if ($driver->hasActiveJob()) {
            throw DeliveryActionException::make('busy_on_job', 409);
        }

        DB::transaction(function () use ($driver, $request) {
            $plate = $request->input('type') === 'bicycle' ? null : $request->input('plate_number');
            $current = $driver->activeVehicle;

            // Same vehicle re-saved with cosmetic edits keeps its verification.
            if ($current && $current->type === $request->input('type') && $current->plate_number === $plate) {
                $current->fill($request->only(['make', 'colour']))->save();

                return;
            }

            $driver->vehicles()->update(['is_active' => false]);
            // Re-activating your own previously registered plate reuses that row (plates are unique).
            $vehicle = $plate ? $driver->vehicles()->where('plate_number', $plate)->first() : null;
            $vehicle ??= new Vehicle(['driver_profile_id' => $driver->id]);
            $vehicle->fill([
                'type' => $request->input('type'),
                'plate_number' => $plate,
                'make' => $request->input('make'),
                'colour' => $request->input('colour'),
                'is_active' => true,
            ]);
            $vehicle->verified_at = null;
            $vehicle->save();

            // A new vehicle must be checked by an admin before going online.
            $driver->forceFill(['is_online' => false])->save();
        });

        return new DriverProfileResource($driver->fresh());
    }

    public function submit(Request $request): DriverProfileResource
    {
        $driver = $this->driverOrFail($request);
        $missing = $driver->missingRequirements();
        if ($missing !== []) {
            throw DeliveryActionException::make('profile_incomplete', 422, [], ['missing' => $missing]);
        }
        if ($driver->isApproved() || $driver->status === 'suspended') {
            throw DeliveryActionException::make('cannot_submit', 409);
        }

        $driver->forceFill(['status' => 'pending', 'submitted_at' => now(), 'rejection_reason' => null])->save();

        return new DriverProfileResource($driver->fresh());
    }

    private function driverOrFail(Request $request): DriverProfile
    {
        return $request->user()->driverProfile ?? throw DeliveryActionException::make('not_a_driver', 403);
    }
}

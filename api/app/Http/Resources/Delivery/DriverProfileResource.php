<?php

namespace App\Http\Resources\Delivery;

use App\Models\DriverProfile;
use App\Support\PrivateFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The driver's own profile. Never includes the full national ID number or
 * the document images themselves, only whether each has been uploaded.
 *
 * @mixin DriverProfile
 */
class DriverProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user', 'activeVehicle']);
        $vehicle = $this->activeVehicle;
        $missing = $this->missingRequirements();
        $licenceExpired = $this->licence_expires_on !== null && $this->licence_expires_on->isPast();

        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'rejection_reason' => $this->rejection_reason,
            'suspended_reason' => $this->suspended_reason,
            'is_online' => $this->is_online,
            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,
            'jobs_completed' => $this->jobs_completed,
            'photo_url' => PrivateFileUrl::for($this->photo),
            'national_id_last4' => $this->national_id_number ? substr($this->national_id_number, -4) : null,
            'licence_number' => $this->licence_number,
            'licence_expires_on' => $this->licence_expires_on?->toDateString(),
            'licence_expired' => $licenceExpired,
            'documents' => [
                'photo' => $this->photo !== null,
                'national_id_image' => $this->national_id_image !== null,
                'licence_image' => $this->licence_image !== null,
            ],
            'vehicle' => $vehicle ? [
                'type' => $vehicle->type,
                'plate_number' => $vehicle->plate_number,
                'make' => $vehicle->make,
                'colour' => $vehicle->colour,
                'verified' => $vehicle->verified_at !== null,
            ] : null,
            'missing_requirements' => $missing,
            'can_submit' => $missing === [] && ($this->submitted_at === null || $this->status === 'rejected'),
            'can_go_online' => $this->isApproved() && $vehicle?->verified_at !== null && ! $licenceExpired,
            'active_job_id' => $this->activeJob()?->id,
            'terms_accepted_at' => $this->terms_accepted_at,
        ];
    }
}

<?php

namespace App\Http\Requests\Delivery;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driverId = $this->user()?->driverProfile?->id ?? 0;

        return [
            'type' => ['required', Rule::in(Vehicle::TYPES)],
            'plate_number' => [
                Rule::requiredIf(fn () => $this->input('type') !== 'bicycle'),
                'nullable', 'string', 'alpha_num:ascii', 'min:4', 'max:12',
                // Another driver's vehicle with this plate blocks it; re-saving your own does not.
                Rule::unique('vehicles', 'plate_number')->where(fn ($q) => $q->where('driver_profile_id', '!=', $driverId)),
            ],
            'make' => ['nullable', 'string', 'max:60'],
            'colour' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plate_number' => Vehicle::normalisePlate($this->input('plate_number'))]);
    }
}

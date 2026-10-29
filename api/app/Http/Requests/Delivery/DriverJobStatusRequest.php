<?php

namespace App\Http\Requests\Delivery;

use App\Services\Delivery\DeliveryJobService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'cancelled' = the driver backs out before pickup (the job is re-dispatched).
            'status' => ['required', Rule::in(['arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered', 'failed', 'cancelled'])],
            'pickup_code' => ['required_if:status,picked_up', 'nullable', 'string', 'max:10'],
            'pin' => ['required_if:status,delivered', 'nullable', 'string', 'digits:4'],
            'proof_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reason' => ['required_if:status,failed', 'nullable', 'string', Rule::in(DeliveryJobService::FAILURE_REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}

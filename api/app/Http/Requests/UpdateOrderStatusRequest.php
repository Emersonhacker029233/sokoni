<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Transition legality + party check happens in the controller/model.
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['accepted', 'ready', 'completed', 'cancelled'])],
            'reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:255'],
        ];
    }
}

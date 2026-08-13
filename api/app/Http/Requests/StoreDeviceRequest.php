<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ];
    }
}

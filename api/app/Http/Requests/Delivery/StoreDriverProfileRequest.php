<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // NIDA numbers are 20 digits. Format only; a human verifies (same as sellers).
            'national_id_number' => ['required', 'string', 'digits:20'],
            'licence_number' => ['nullable', 'string', 'max:40'],
            'licence_expires_on' => ['nullable', 'date', 'after:today'],
            'accept_terms' => ['required', 'accepted'],
            'terms_version' => ['required', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'national_id_number' => preg_replace('/[^0-9]/', '', (string) $this->input('national_id_number')),
        ]);
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Onboarding wizard step 2: map pin + reverse-geocoded, user-confirmed address. */
class SellerOnboardLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
        ];
    }
}

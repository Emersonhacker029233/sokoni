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
            // Nullable, not required: the app's own map picker always sends
            // both (unaffected by this), but the website has no Google Maps
            // key configured and falls back to region/district/address with
            // best-effort server-side geocoding — see
            // Web\Account\SellerRegistrationController — so a missing pin
            // must never block submission here.
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
        ];
    }
}

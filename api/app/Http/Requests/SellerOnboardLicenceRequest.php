<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Onboarding wizard step 4: business/trading licence, image or PDF, max 5MB. */
class SellerOnboardLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        return [
            'licence_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}

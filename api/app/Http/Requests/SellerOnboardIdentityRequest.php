<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Onboarding wizard step 3: NIDA number + ID photo. */
class SellerOnboardIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        return [
            'nida_number' => ['required', 'digits:20'],
            'nida_image' => ['required', 'image', 'max:5120'],
        ];
    }
}

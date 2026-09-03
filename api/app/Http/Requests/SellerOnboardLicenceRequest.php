<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Onboarding wizard step 4: business/trading licence, image or PDF, max
 * 5MB — now optional (client request, NIDA-only verification): NIDA
 * number + ID photo (step 3) are the actual basis of verification and
 * always required; the licence is an optional extra, visible to admins
 * if a seller chooses to provide one, never a precondition for review.
 */
class SellerOnboardLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        return [
            'licence_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}

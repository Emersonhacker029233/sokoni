<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Onboarding wizard's identity step: the NIDA number alone (client
 * request, B1: removed the ID photo upload entirely — the number stays
 * the required, actual basis of verification, a human reviewer checks it
 * without needing a scanned image on file).
 */
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
        ];
    }
}

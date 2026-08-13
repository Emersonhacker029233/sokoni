<?php

namespace App\Http\Requests;

use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Onboarding wizard step 1: business details. */
class SellerOnboardBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        // One seller profile per user — a buyer upgrading via "Start
        // selling" (CLAUDE.md feature 6) hits this once.
        return ! $this->user()->isSeller();
    }

    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:255'],
            'handle' => [
                'required', 'string',
                'regex:'.SellerProfile::HANDLE_PATTERN,
                Rule::notIn(SellerProfile::RESERVED_HANDLES),
                Rule::unique('seller_profiles', 'handle'),
            ],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+255[67]\d{8}$/'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Final step of the "Create an account" flow (CLAUDE.md restructure,
 * 2026-08-25) — verifies the OTP and creates the User (and, for a seller,
 * the SellerProfile) in one request. Deliberately reuses the exact same
 * field rules the existing 4-step seller onboarding wizard's first two
 * steps already enforce (SellerOnboardBusinessRequest/
 * SellerOnboardLocationRequest) — same `SellerProfile::HANDLE_PATTERN`/
 * `RESERVED_HANDLES` constants, same literal shop_name/whatsapp/region/
 * district/address rules — so the two flows can never drift apart, per
 * instruction not to write new validation rules where these already exist.
 */
class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSeller = $this->input('account_intent') === 'sell';
        $sellerOnly = $isSeller ? 'required' : 'prohibited';

        return [
            'phone' => ['required', 'string', 'regex:/^\+255[67]\d{8}$/'],
            'code' => ['required', 'string', 'size:6'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'marketing_consent' => ['sometimes', 'boolean'],
            'account_intent' => ['required', 'string', Rule::in(['buy', 'sell'])],
            'terms_version' => ['required', 'string', 'max:20'],

            'shop_name' => [$sellerOnly, 'string', 'max:255'],
            'handle' => [
                $sellerOnly, 'string',
                'regex:'.SellerProfile::HANDLE_PATTERN,
                Rule::notIn(SellerProfile::RESERVED_HANDLES),
                Rule::unique('seller_profiles', 'handle'),
            ],
            'category_id' => [$sellerOnly, 'integer', 'exists:categories,id'],
            'region' => [$sellerOnly, 'string', 'max:100'],
            'district' => [$sellerOnly, 'string', 'max:100'],
            'address' => [$sellerOnly, 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+255[67]\d{8}$/'],
        ];
    }
}

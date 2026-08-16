<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['google', 'apple'])],
            // The provider's ID/access token (Google id_token, Apple
            // identityToken) — verified server-side in
            // HttpSocialAuthVerifier, never trusted as-is.
            'token' => ['required', 'string'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guard-agnostic (like StoreReviewRequest/StoreReportRequest before it) —
 * reused directly by both the website's account settings controller (web
 * guard) and the app's own `PATCH /auth/profile` endpoint (sanctum guard),
 * so "can this email be taken?" is checked identically everywhere.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'locale' => ['sometimes', 'string', 'in:en,sw'],
        ];
    }
}

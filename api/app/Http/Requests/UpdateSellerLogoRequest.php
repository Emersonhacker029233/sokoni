<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Shop logo/avatar (CLAUDE.md Parts 3-4) — owner-only, same as every other onboarding upload step. */
class UpdateSellerLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'max:2048'],
        ];
    }
}

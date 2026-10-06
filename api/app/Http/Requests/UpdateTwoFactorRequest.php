<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Settings' "Require a code every time I sign in" toggle — CLAUDE.md Part D 2.4, off by default. */
class UpdateTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}

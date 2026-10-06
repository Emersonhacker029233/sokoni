<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Live availability check while typing — CLAUDE.md Part D 2.1. Format-checked here; uniqueness is checked in the controller so a taken-but-valid-format username still returns a clean `{available: false}`, not a validation error. */
class CheckUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'regex:'.User::USERNAME_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => '3-20 characters, lowercase letters, numbers and underscores only.',
        ];
    }
}

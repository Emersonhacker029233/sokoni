<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CLAUDE.md Part D 2.6 — the one-time prompt an existing (pre-rework)
 * account completes to gain a username/password, shown after signing in
 * the old way. Also reusable anywhere else a signed-in user sets
 * credentials for the first time; not used for *changing* an
 * already-set username (not a requested feature) or password (that's
 * the separate forgot-password reset flow, which is unauthenticated by
 * necessity).
 */
class SetCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string',
                'regex:'.User::USERNAME_PATTERN,
                Rule::notIn(User::RESERVED_USERNAMES),
                Rule::unique('users', 'username'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => '3-20 characters, lowercase letters, numbers and underscores only.',
            'username.not_in' => 'That username is reserved — please choose another.',
            'username.unique' => 'That username is already taken.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 2 of CLAUDE.md Part D 2.5 — the SMS code plus the new password in one request. A success here revokes every other session (PasswordAuthController::forgotPasswordReset()). */
class ForgotPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}

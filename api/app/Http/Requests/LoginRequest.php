<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Username-or-phone + password (CLAUDE.md Part D 2.2) — replaces
 * send-code-first as the primary sign-in path. `device_token` is the
 * "recognised device" token stored on-device from a prior full sign-in
 * (see TrustedDevice); omitted entirely on a device that's never
 * completed one. No `code` field here — a code is only ever requested
 * *after* a correct password (PasswordAuthController::login()), never
 * supplied up front.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_token' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Second step of PasswordAuthController::login() — the SMS code sent after a correct password. Succeeding issues a new "recognised device" token (TrustedDevice) so the next sign-in from this device can skip this step. */
class VerifyLoginRequest extends FormRequest
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
        ];
    }
}

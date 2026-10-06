<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 1 of CLAUDE.md Part D 2.5 — username or phone. Always answered identically whether or not the account exists (PasswordAuthController), so this alone can't be used to enumerate accounts. */
class ForgotPasswordRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
        ];
    }
}

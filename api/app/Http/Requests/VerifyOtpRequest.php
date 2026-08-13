<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isNewUser = ! User::query()->where('phone', $this->input('phone'))->exists();

        return [
            'phone' => ['required', 'string', 'regex:/^\+255[67]\d{8}$/'],
            'code' => ['required', 'string', 'size:6'],
            // Full name is only required the first time this phone signs in.
            'name' => [$isNewUser ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }
}

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

    /** Part 2 (client feedback): see RequestOtpRequest::messages() — same reasoning, same wording. */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Tanzanian mobile number, e.g. 712 345 678 or 0712 345 678.',
        ];
    }
}

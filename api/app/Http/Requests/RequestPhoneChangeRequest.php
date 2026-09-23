<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Part 4 (client feedback): "phone number ... changing it needs
 * re-verification" — first step, sends an OTP to the *new* number. Reuses
 * PhoneOtpService (same TTL, same SmsGateway) so a phone change goes
 * through the identical code path as signing in with a new number, just
 * scoped to an already-authenticated user instead of creating an account.
 */
class RequestPhoneChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\+255[67]\d{8}$/',
                Rule::notIn([$this->user()->phone]),
                Rule::unique((new User)->getTable(), 'phone'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Tanzanian mobile number, e.g. 712 345 678 or 0712 345 678.',
            'phone.not_in' => 'That is already your current number.',
            'phone.unique' => 'This number already has an account.',
        ];
    }
}

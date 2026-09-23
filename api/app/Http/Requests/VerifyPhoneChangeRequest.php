<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Second step of a phone number change — re-checks the same uniqueness
 * rule as RequestPhoneChangeRequest (a second account could have claimed
 * the number in the few minutes the code was outstanding) immediately
 * before it becomes the user's number of record.
 */
class VerifyPhoneChangeRequest extends FormRequest
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
            'code' => ['required', 'string', 'size:6'],
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

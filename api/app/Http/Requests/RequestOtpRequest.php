<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // E.164 Tanzanian mobile number, e.g. +255754123456.
            'phone' => ['required', 'string', 'regex:/^\+255[67]\d{8}$/'],
            // Optional — the API has no locale-detection middleware the way
            // the website does (SetWebLocale), so a client that knows the
            // user's language can say so; defaults to English otherwise.
            // See AuthController::requestOtp()/PhoneOtpService.
            'locale' => ['nullable', 'string', Rule::in(['en', 'sw'])],
        ];
    }

    /**
     * Part 2 (client feedback): "update the validation message so it
     * describes the local format the user is actually entering" — both
     * clients compose the full +255... value from a fixed prefix plus
     * whatever the visitor typed, so describe the LOCAL digits (what a
     * validation failure would actually mean to them), not the E.164
     * shape they never see or type themselves.
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Tanzanian mobile number, e.g. 712 345 678 or 0712 345 678.',
        ];
    }
}

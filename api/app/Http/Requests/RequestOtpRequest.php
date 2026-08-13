<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}

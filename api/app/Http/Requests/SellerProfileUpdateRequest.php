<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellerProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        return [
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'regex:/^\+255[67]\d{8}$/'],
            'show_whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}

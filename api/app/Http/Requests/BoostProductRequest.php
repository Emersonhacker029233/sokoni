<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoostProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return [
            'is_sponsored' => ['required', 'boolean'],
            // Only meaningful when turning boosting on.
            'duration_days' => ['required_if:is_sponsored,true', 'integer', 'between:1,30'],
            'contact_method' => ['required_if:is_sponsored,true', Rule::in(['chat', 'whatsapp', 'call'])],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'condition' => ['sometimes', Rule::in(['new', 'used'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            // Radius presets from CLAUDE.md feature 1: 1/5/10/25km, or
            // omitted entirely for "All".
            'radius_km' => ['nullable', 'numeric', Rule::in([1, 5, 10, 25])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(['nearby', 'trending', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

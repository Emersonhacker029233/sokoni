<?php

namespace App\Http\Requests\Returns;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /orders/{order}/returns (multipart): reason, 1-3 photos, optional comment. */
class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(ReturnRequest::REASONS)],
            'comment' => ['nullable', 'string', 'max:1000'],
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}

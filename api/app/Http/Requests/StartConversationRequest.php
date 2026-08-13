<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seller_id' => ['required', 'integer', 'exists:seller_profiles,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}

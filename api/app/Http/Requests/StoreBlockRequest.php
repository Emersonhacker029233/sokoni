<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Block a person directly (`user_id`) or the owner of a shop (`seller_id`). Exactly one of the two. */
class StoreBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required_without:seller_id', 'prohibits:seller_id', 'integer'],
            'seller_id' => ['required_without:user_id', 'integer'],
            'reason' => ['nullable', 'string', Rule::in(array_keys(Report::REASONS))],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'delivery_method' => ['required', Rule::in(['pickup', 'delivery'])],
            'address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:255'],
            'delivery_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cash_on_delivery', 'pay_on_pickup'])],
        ];
    }

    /** Cart holds items from one seller at a time (CLAUDE.md feature 8). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productIds = collect($this->input('items', []))->pluck('product_id')->filter();
            if ($productIds->isEmpty()) {
                return;
            }

            $sellerCount = Product::query()
                ->whereIn('id', $productIds)
                ->distinct('seller_id')
                ->count('seller_id');

            if ($sellerCount > 1) {
                $validator->errors()->add('items', 'All items in one order must be from the same seller.');
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed_price'])],
            'discount_value' => ['required', 'numeric', 'min:1'],
            // A countdown length, not a date range (CLAUDE.md: "countdown
            // 1-7 days") — the offer always starts immediately on creation;
            // the controller computes starts_at/ends_at from this.
            'duration_days' => ['required', 'integer', 'between:1,7'],
        ];
    }

    /** The product must be the acting seller's own, and the discount must actually be a discount. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $product = Product::query()->find($this->input('product_id'));
            if (! $product) {
                return;
            }

            if ($product->seller_id !== $this->user()->sellerProfileId()) {
                $validator->errors()->add('product_id', 'You can only run an Offer on your own Listings.');

                return;
            }

            $type = $this->input('discount_type');
            $value = (float) $this->input('discount_value');

            if ($type === 'percent' && ($value < 1 || $value > 90)) {
                $validator->errors()->add('discount_value', 'A percentage discount must be between 1 and 90.');
            }

            if ($type === 'fixed_price' && $value >= (float) $product->price) {
                $validator->errors()->add('discount_value', 'The offer price must be less than the current price.');
            }
        });
    }
}

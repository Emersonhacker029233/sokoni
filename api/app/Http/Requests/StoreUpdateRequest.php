<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A seller profile is required to post an Update — same reasoning
        // as ProductStoreRequest. The non-nullable seller_id FK is the
        // real, schema-level guarantee; this just makes the 403 meaningful.
        return $this->user()?->isSeller() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['image', 'video'])],
            'file' => [
                'required',
                'file',
                Rule::when($this->input('type') === 'video', ['mimetypes:video/mp4,video/quicktime', 'max:20480']),
                Rule::when($this->input('type') === 'image', ['image', 'max:8192']),
            ],
            'thumbnail' => ['required_if:type,video', 'nullable', 'image', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:280'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }

    /** If a product is referenced, it must be one of the acting seller's own Listings. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productId = $this->input('product_id');
            if (! $productId) {
                return;
            }

            $product = Product::query()->find($productId);
            if ($product && $product->seller_id !== $this->user()->sellerProfileId()) {
                $validator->errors()->add('product_id', 'You can only post an Update about your own Listings.');
            }
        });
    }
}

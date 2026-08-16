<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShowcaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            // Same caps as ProductMediaStoreRequest's video branch —
            // CLAUDE.md feature 7: max 60s, ~20MB, client-compressed.
            'file' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime', 'max:20480'],
            // Poster frame, client-extracted — same reasoning as product
            // video media (no ffmpeg dependency server-side).
            'thumbnail' => ['required', 'image', 'max:4096'],
            'duration' => ['required', 'integer', 'min:1', 'max:60'],
            'caption' => ['nullable', 'string', 'max:280'],
        ];
    }

    /** The product must be the acting seller's own — a Showcase is definitionally a video of one of their Listings. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $product = Product::query()->find($this->input('product_id'));
            if ($product && $product->seller_id !== $this->user()->sellerProfileId()) {
                $validator->errors()->add('product_id', 'You can only create a Showcase for your own Listings.');
            }
        });
    }
}

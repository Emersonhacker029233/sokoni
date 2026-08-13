<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReplyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Review $review */
        $review = $this->route('review');

        return $this->user()->sellerProfileId() === $review->seller_id;
    }

    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'max:1000'],
        ];
    }

    /** Sellers reply once per review (CLAUDE.md feature 2). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Review $review */
            $review = $this->route('review');
            if ($review->hasReply()) {
                $validator->errors()->add('reply', 'This review already has a reply.');
            }
        });
    }
}

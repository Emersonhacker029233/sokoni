<?php

namespace App\Http\Requests;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CommentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }

    /** One level of replies only: a reply's parent must itself be a root comment on the same product. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_id');
            if (! $parentId) {
                return;
            }

            $parent = Comment::query()->find($parentId);
            if (! $parent) {
                return;
            }

            if ($parent->product_id !== (int) $this->route('product')->id) {
                $validator->errors()->add('parent_id', 'That comment is not on this product.');

                return;
            }

            if ($parent->parent_id !== null) {
                $validator->errors()->add('parent_id', 'Replies can only be one level deep.');
            }
        });
    }
}

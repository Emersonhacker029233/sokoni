<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $order->buyer_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The entire defence against fake reviews (CLAUDE.md feature 2): only
     * a buyer with a COMPLETED order from that seller may review it, and
     * only once (reviews.order_id is unique at the schema level too).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Order $order */
            $order = $this->route('order');

            if (! $order->isCompleted()) {
                $validator->errors()->add('order', 'Only completed orders can be reviewed.');
            }

            if ($order->review()->exists()) {
                $validator->errors()->add('order', 'This order has already been reviewed.');
            }
        });
    }
}

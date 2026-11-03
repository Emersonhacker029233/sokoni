<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Services\Delivery\DeliveryUnavailableException;
use App\Services\Delivery\FeeCalculator;
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
            // Sokoni 1.1 dispatch only: who the rider calls at the drop-off
            // (E.164). Optional for the API; empty means the buyer's phone.
            'delivery_phone' => ['nullable', 'string', 'regex:/^\+255[67]\d{8}$/'],
            'payment_method' => ['required', Rule::in(['cash_on_delivery', 'pay_on_pickup'])],
            // Sokoni 1.1+ only (docs/delivery/PLAN.md). Ignored entirely while
            // DELIVERY_ENABLED is false; Sokoni 1.0 never sends it.
            'fulfilment' => ['nullable', Rule::in(['dispatch'])],
        ];
    }

    /** True when this checkout opts into Sokoni Driver delivery and the feature is on. */
    public function wantsDispatch(): bool
    {
        return (bool) config('delivery.enabled')
            && $this->input('fulfilment') === 'dispatch'
            && $this->input('delivery_method') === 'delivery';
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

            if ($this->wantsDispatch()) {
                $this->validateDispatch($validator, $productIds->first());
            }
        });
    }

    /** Dispatch needs exact coordinates on both ends and a drop-off inside the delivery area. */
    private function validateDispatch(Validator $validator, mixed $productId): void
    {
        if ($this->input('delivery_lat') === null || $this->input('delivery_lng') === null) {
            $validator->errors()->add('delivery_lat', __('delivery.errors.dropoff_location_required'));

            return;
        }

        $seller = Product::query()->find($productId)?->seller;
        if ($seller?->lat === null || $seller?->lng === null) {
            $validator->errors()->add('fulfilment', __('delivery.errors.shop_has_no_location'));

            return;
        }

        try {
            app(FeeCalculator::class)->quote(
                (float) $seller->lat, (float) $seller->lng,
                (float) $this->input('delivery_lat'), (float) $this->input('delivery_lng'),
            );
        } catch (DeliveryUnavailableException $e) {
            $validator->errors()->add('delivery_lat', $e->getMessage());
        }
    }
}

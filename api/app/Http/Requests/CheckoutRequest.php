<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\DeliveryQuoter;
use App\Services\Delivery\DeliveryUnavailableException;
use App\Support\DeliveryAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    private ?SellerProfile $shop = null;

    private bool $shopResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
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

        // Bolt delivery (docs/delivery/BOLT.md). Only validated for users the
        // dark launch includes; for everyone else these fields are ignored
        // exactly as unknown fields always were.
        if (DeliveryAccess::deliveryEnabledFor($this->user())) {
            // Who the Bolt driver hands the parcel to (E.164). Optional; empty means the buyer's phone.
            $rules['delivery_phone'] = ['nullable', 'string', 'regex:/^\+255[67]\d{8}$/'];
            $rules['fulfilment'] = ['nullable', Rule::in(['dispatch'])];
        }

        return $rules;
    }

    /**
     * True when this checkout is a Bolt delivery: the app asked for it,
     * delivery is chosen, and BOTH the buyer and the shop owner are inside
     * the launch (BOLT_TEST_PHONES). Otherwise the order is an ordinary one.
     */
    public function wantsDispatch(): bool
    {
        return $this->input('fulfilment') === 'dispatch'
            && $this->input('delivery_method') === 'delivery'
            && DeliveryAccess::orderEligible($this->user(), $this->shop());
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

                return;
            }

            if ($this->wantsDispatch()) {
                $this->validateDispatch($validator);
            }
        });
    }

    /** The shop of the cart's first product (one shop per cart). */
    public function shop(): ?SellerProfile
    {
        if (! $this->shopResolved) {
            $this->shopResolved = true;
            $productId = collect($this->input('items', []))->pluck('product_id')->filter()->first();
            $this->shop = $productId === null ? null : Product::query()->with('seller.user')->find($productId)?->seller;
        }

        return $this->shop;
    }

    /**
     * A Bolt delivery needs exact coordinates on both ends, a drop-off
     * inside the delivery area, and Bolt serving the trip. If Bolt can't be
     * asked right now the order still goes through: it is priced again when
     * the shop accepts.
     */
    private function validateDispatch(Validator $validator): void
    {
        if ($this->input('delivery_lat') === null || $this->input('delivery_lng') === null) {
            $validator->errors()->add('delivery_lat', __('delivery.errors.dropoff_location_required'));

            return;
        }

        try {
            app(DeliveryQuoter::class)->quote($this->shop(), (float) $this->input('delivery_lat'), (float) $this->input('delivery_lng'));
        } catch (DeliveryUnavailableException $e) {
            $validator->errors()->add($e->reason === 'shop_has_no_location' ? 'fulfilment' : 'delivery_lat', $e->getMessage());
        } catch (BoltApiException) {
            // Bolt unreachable: accept the order; the job's quote retries after acceptance.
        }
    }
}

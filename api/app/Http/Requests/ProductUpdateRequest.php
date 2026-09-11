<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Support\VehicleMakes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * Only requires make/model when THIS request is the one setting the
     * category to Cars — editing an already-Cars product's price without
     * touching category_id (`category_id` is `sometimes` here, unlike
     * store's `required`) never forces make/model to be resent; the
     * existing ProductAttribute rows are simply left alone in that case
     * (see ProductController::update()).
     */
    private function isCarsCategory(): bool
    {
        return $this->filled('category_id') && (Category::find($this->input('category_id'))?->isCars() ?? false);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'condition' => ['sometimes', Rule::in(['new', 'used'])],
            'is_active' => ['sometimes', 'boolean'],
            'make' => [
                Rule::requiredIf(fn () => $this->isCarsCategory()),
                'nullable', 'string', Rule::in(VehicleMakes::makes()),
            ],
            'model' => [
                Rule::requiredIf(fn () => $this->isCarsCategory()),
                'nullable', 'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $make = $this->input('make');
                    if ($value && $make && ! VehicleMakes::isValidModel($make, $value)) {
                        $fail('The selected model does not belong to the selected make.');
                    }
                },
            ],
            // C4 (tester feedback): Year, the third Cars dropdown — a flat
            // 1990-to-current range (see DECISIONS.md), not filtered by
            // make/model, so no cross-field check like model's above.
            'year' => [
                Rule::requiredIf(fn () => $this->isCarsCategory()),
                'nullable', 'integer', 'between:1990,'.date('Y'),
            ],
        ];
    }
}

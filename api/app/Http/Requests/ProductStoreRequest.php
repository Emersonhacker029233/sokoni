<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Support\VehicleMakes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A seller profile is required to list products — enforced here so
        // the 403 is meaningful, on top of the route's auth:sanctum middleware.
        return $this->user()?->isSeller() ?? false;
    }

    private function isCarsCategory(): bool
    {
        return Category::find($this->input('category_id'))?->isCars() ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'condition' => ['required', Rule::in(['new', 'used'])],
            // C3 (tester feedback): Make/Model are attributes of a Cars
            // listing, not a third category level — required exactly
            // when posting into the Cars category, ignored otherwise.
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
        ];
    }
}

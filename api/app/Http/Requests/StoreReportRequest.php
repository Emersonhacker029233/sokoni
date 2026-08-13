<?php

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /** Client-facing type names, mapped to model classes for the reportable_type column. */
    public const REPORTABLE_TYPES = [
        'product' => Product::class,
        'shop' => SellerProfile::class,
        'message' => Message::class,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportable_type' => ['required', Rule::in(array_keys(self::REPORTABLE_TYPES))],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

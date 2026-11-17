<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Client-facing type names, mapped to model classes for the
     * reportable_type column. Since 1.0 (9) every kind of user content
     * can be reported (Apple Guideline 1.2), not just products, shops,
     * messages and comments.
     */
    public const REPORTABLE_TYPES = Report::TYPES;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportable_type' => ['required', Rule::in(array_keys(self::REPORTABLE_TYPES))],
            'reportable_id' => ['required', 'integer'],
            // A code from Report::REASONS from 1.0 (9) on; older builds send a translated label.
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

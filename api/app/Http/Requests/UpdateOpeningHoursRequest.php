<?php

namespace App\Http\Requests;

use App\Support\OpeningHours;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sellers had no way to edit opening hours at all (tester feedback A6) —
 * `App\Support\OpeningHours`'s own docblock claims "the app's Edit Profile
 * screen writes this same shape", but no such screen or endpoint actually
 * exists anywhere in this codebase (checked directly, not assumed) — a
 * stale claim in the same vein as several others logged in DECISIONS.md.
 * This is the first real writer of `seller_profiles.opening_hours`.
 */
class UpdateOpeningHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('seller'));
    }

    public function rules(): array
    {
        $rules = [];

        foreach (OpeningHours::DAYS as $day) {
            $rules["hours.{$day}.closed"] = ['nullable', 'boolean'];
            $rules["hours.{$day}.open"] = ['required_unless:hours.'.$day.'.closed,1', 'nullable', 'date_format:H:i'];
            $rules["hours.{$day}.close"] = ['required_unless:hours.'.$day.'.closed,1', 'nullable', 'date_format:H:i', 'after:hours.'.$day.'.open'];
        }

        return $rules;
    }

    /** @return array<string, array{open: string, close: string}|null> */
    public function normalizedHours(): array
    {
        $result = [];
        foreach (OpeningHours::DAYS as $day) {
            $dayInput = $this->input("hours.{$day}");
            $result[$day] = (! empty($dayInput['closed']))
                ? null
                : ['open' => $dayInput['open'], 'close' => $dayInput['close']];
        }

        return $result;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Part 4 (client feedback): Settings' Notifications section — toggles for
 * orders, messages, offers from followed shops, and marketing. Every field
 * is optional per request so the client can flip one switch at a time
 * without having to resend the whole set.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notify_orders' => ['sometimes', 'boolean'],
            'notify_messages' => ['sometimes', 'boolean'],
            'notify_offers' => ['sometimes', 'boolean'],
            'notify_marketing' => ['sometimes', 'boolean'],
        ];
    }
}

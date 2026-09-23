<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Part 4 (client feedback): profile photo upload — own account only, same size limit as a seller's shop logo. */
class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'max:2048'],
        ];
    }
}

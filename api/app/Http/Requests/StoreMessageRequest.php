<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        return $conversation->involves($this->user());
    }

    public function rules(): array
    {
        return [
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:2000'],
            'attachment' => ['required_without:body', 'nullable', 'image', 'max:5120'],
        ];
    }
}

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
            // Part 4 (client feedback): "tapping a document opens or
            // downloads it appropriately" — was image-only before this,
            // which made a document attachment in chat impossible to
            // ever produce in the first place. Widened to the common
            // document formats a buyer/seller would realistically share
            // (a receipt, an invoice, a spec sheet), not every mimetype
            // PHP's validator recognises.
            'attachment' => ['required_without:body', 'nullable', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx', 'max:5120'],
        ];
    }
}
